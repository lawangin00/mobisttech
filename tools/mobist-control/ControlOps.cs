using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Management;
using System.Net.NetworkInformation;
using System.Runtime.InteropServices;
using System.Threading;
using System.Windows.Forms;

namespace MobiSTControl
{
    internal sealed class ServiceDefinition
    {
        public string Role;
        public string DisplayName;
        public string ProjectPath;
        public string RunnerPath;
        public int Port;
    }

    internal sealed class OwnedRecord
    {
        public int ProcessId;
        public long StartTicks;
        public string RunnerPath;
    }

    internal enum ServiceState
    {
        Offline,
        Starting,
        Online,
        Blocked,
        Stale
    }

    internal sealed class ServiceStatus
    {
        public ServiceState State;
        public string Text;
        public int ListenerPid;
        public int RootPid;
    }

    internal static class ControlOps
    {
        public const string ProjectRoot = @"C:\mobisttech";
        public const string BackendProject = @"C:\mobisttech\backend";
        public const string WebsiteProject = @"C:\mobisttech\website";
        public const string ControlProject = @"C:\mobisttech\tools\mobist-control";
        public const int BackendPort = 18080;
        public const int VitePort = 15173;
        public const int WebsitePort = 13000;

        private static readonly ServiceDefinition BackendHttp = NewService(
            "backend_http", "Backend HTTP", BackendProject, "run-backend-http.cmd", BackendPort);
        private static readonly ServiceDefinition BackendVite = NewService(
            "backend_vite", "Backend Vite", BackendProject, "run-backend-vite.cmd", VitePort);
        private static readonly ServiceDefinition WebsiteNext = NewService(
            "website_next", "Website Next.js", WebsiteProject, "run-website-next.cmd", WebsitePort);

        private static ServiceDefinition NewService(string role, string display, string project, string runner, int port)
        {
            return new ServiceDefinition
            {
                Role = role,
                DisplayName = display,
                ProjectPath = project,
                RunnerPath = Path.Combine(ControlProject, runner),
                Port = port
            };
        }

        public static string StateRoot
        {
            get
            {
                string path = Path.Combine(
                    Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                    "mobiST Control",
                    "owned");
                Directory.CreateDirectory(path);
                return path;
            }
        }

        private static string RecordPath(ServiceDefinition service)
        {
            return Path.Combine(StateRoot, service.Role + ".state");
        }

        private static void WriteRecord(ServiceDefinition service, Process process)
        {
            string[] lines = new[]
            {
                process.Id.ToString(),
                process.StartTime.ToUniversalTime().Ticks.ToString(),
                service.RunnerPath
            };
            File.WriteAllLines(RecordPath(service), lines);
        }

        private static OwnedRecord ReadRecord(ServiceDefinition service)
        {
            string path = RecordPath(service);
            if (!File.Exists(path)) return null;
            try
            {
                string[] lines = File.ReadAllLines(path);
                int pid;
                long ticks;
                if (lines.Length < 3 ||
                    !int.TryParse(lines[0].Trim(), out pid) ||
                    !long.TryParse(lines[1].Trim(), out ticks))
                {
                    File.Delete(path);
                    return null;
                }
                return new OwnedRecord
                {
                    ProcessId = pid,
                    StartTicks = ticks,
                    RunnerPath = lines[2].Trim()
                };
            }
            catch
            {
                return null;
            }
        }

        private static void DeleteRecord(ServiceDefinition service)
        {
            try
            {
                string path = RecordPath(service);
                if (File.Exists(path)) File.Delete(path);
            }
            catch { }
        }

        private static string ProcessCommandLine(int pid)
        {
            try
            {
                using (ManagementObjectSearcher searcher = new ManagementObjectSearcher(
                    "SELECT CommandLine FROM Win32_Process WHERE ProcessId=" + pid))
                {
                    foreach (ManagementObject item in searcher.Get())
                        return Convert.ToString(item["CommandLine"]) ?? "";
                }
            }
            catch { }
            return "";
        }

        private static int ParentPid(int pid)
        {
            try
            {
                using (ManagementObjectSearcher searcher = new ManagementObjectSearcher(
                    "SELECT ParentProcessId FROM Win32_Process WHERE ProcessId=" + pid))
                {
                    foreach (ManagementObject item in searcher.Get())
                        return Convert.ToInt32(item["ParentProcessId"]);
                }
            }
            catch { }
            return 0;
        }

        private static bool IsDescendantOrSame(int candidatePid, int rootPid)
        {
            if (candidatePid <= 0 || rootPid <= 0) return false;
            int current = candidatePid;
            var seen = new HashSet<int>();
            for (int i = 0; i < 20 && current > 0 && seen.Add(current); i++)
            {
                if (current == rootPid) return true;
                current = ParentPid(current);
            }
            return false;
        }

        private static bool TryGetOwnedProcess(ServiceDefinition service, out Process owned)
        {
            owned = null;
            OwnedRecord record = ReadRecord(service);
            if (record == null) return false;

            if (!string.Equals(
                Path.GetFullPath(record.RunnerPath),
                Path.GetFullPath(service.RunnerPath),
                StringComparison.OrdinalIgnoreCase))
            {
                DeleteRecord(service);
                return false;
            }

            try
            {
                Process process = Process.GetProcessById(record.ProcessId);
                long ticks = process.StartTime.ToUniversalTime().Ticks;
                if (ticks != record.StartTicks)
                {
                    DeleteRecord(service);
                    process.Dispose();
                    return false;
                }

                string commandLine = ProcessCommandLine(record.ProcessId);
                if (commandLine.IndexOf(service.RunnerPath, StringComparison.OrdinalIgnoreCase) < 0)
                {
                    DeleteRecord(service);
                    process.Dispose();
                    return false;
                }

                owned = process;
                return true;
            }
            catch
            {
                DeleteRecord(service);
                return false;
            }
        }

        public static int GetListeningPid(int port)
        {
            try
            {
                ProcessStartInfo psi = new ProcessStartInfo("netstat.exe", "-ano -p tcp");
                psi.UseShellExecute = false;
                psi.CreateNoWindow = true;
                psi.RedirectStandardOutput = true;
                using (Process process = Process.Start(psi))
                {
                    string output = process.StandardOutput.ReadToEnd();
                    process.WaitForExit(4000);
                    foreach (string raw in output.Split(new[] { '\r', '\n' }, StringSplitOptions.RemoveEmptyEntries))
                    {
                        string line = raw.Trim();
                        if (!line.StartsWith("TCP", StringComparison.OrdinalIgnoreCase) ||
                            line.IndexOf("LISTENING", StringComparison.OrdinalIgnoreCase) < 0) continue;
                        string[] parts = line.Split((char[])null, StringSplitOptions.RemoveEmptyEntries);
                        if (parts.Length < 5) continue;
                        string local = parts[1];
                        int index = local.LastIndexOf(':');
                        int parsedPort;
                        int pid;
                        if (index >= 0 &&
                            int.TryParse(local.Substring(index + 1), out parsedPort) &&
                            parsedPort == port &&
                            int.TryParse(parts[4], out pid))
                            return pid;
                    }
                }
            }
            catch { }
            return 0;
        }

        private static ServiceStatus GetServiceStatus(ServiceDefinition service)
        {
            Process owned;
            bool ownsRoot = TryGetOwnedProcess(service, out owned);
            int rootPid = ownsRoot ? owned.Id : 0;
            int listenerPid = GetListeningPid(service.Port);

            if (owned != null) owned.Dispose();

            if (ownsRoot)
            {
                if (listenerPid > 0)
                {
                    if (IsDescendantOrSame(listenerPid, rootPid))
                    {
                        return new ServiceStatus
                        {
                            State = ServiceState.Online,
                            Text = service.DisplayName + " Online (owned PID " + rootPid + ", listener " + listenerPid + ")",
                            ListenerPid = listenerPid,
                            RootPid = rootPid
                        };
                    }

                    return new ServiceStatus
                    {
                        State = ServiceState.Blocked,
                        Text = service.DisplayName + " Blocked: port " + service.Port +
                            " is owned by unrelated PID " + listenerPid + "; owned launcher PID " + rootPid + " was not trusted to control it.",
                        ListenerPid = listenerPid,
                        RootPid = rootPid
                    };
                }

                return new ServiceStatus
                {
                    State = ServiceState.Starting,
                    Text = service.DisplayName + " Starting/Not Ready (owned PID " + rootPid + ")",
                    RootPid = rootPid
                };
            }

            if (listenerPid > 0)
            {
                return new ServiceStatus
                {
                    State = ServiceState.Blocked,
                    Text = service.DisplayName + " Blocked: port " + service.Port +
                        " is occupied by unowned PID " + listenerPid + ". Nothing will be stopped by port alone.",
                    ListenerPid = listenerPid
                };
            }

            return new ServiceStatus
            {
                State = ServiceState.Offline,
                Text = service.DisplayName + " Offline"
            };
        }

        private static string FindOnPath(string fileName)
        {
            string path = Environment.GetEnvironmentVariable("PATH") ?? "";
            foreach (string part in path.Split(';'))
            {
                try
                {
                    string candidate = Path.Combine(part.Trim().Trim('"'), fileName);
                    if (File.Exists(candidate)) return candidate;
                }
                catch { }
            }
            return null;
        }

        private static string Preflight(ServiceDefinition service)
        {
            if (!Directory.Exists(service.ProjectPath))
                return service.DisplayName + ": project path missing: " + service.ProjectPath;
            if (!File.Exists(service.RunnerPath))
                return service.DisplayName + ": runner missing: " + service.RunnerPath;

            if (service.Role == "backend_http" && !File.Exists(Path.Combine(service.ProjectPath, "artisan")))
                return service.DisplayName + ": artisan missing.";
            if (service.Role == "backend_http" && !File.Exists(@"C:\php\php.exe"))
                return service.DisplayName + ": C:\\php\\php.exe missing.";
            if (service.Role != "backend_http" && string.IsNullOrEmpty(FindOnPath("npm.cmd")))
                return service.DisplayName + ": npm.cmd is not available on PATH.";

            return null;
        }

        private static string StartService(ServiceDefinition service)
        {
            string preflight = Preflight(service);
            if (preflight != null) return preflight;

            ServiceStatus current = GetServiceStatus(service);
            if (current.State == ServiceState.Online)
                return service.DisplayName + " is already Online; duplicate start skipped.";
            if (current.State == ServiceState.Starting)
                return service.DisplayName + " already has an owned launcher; duplicate start skipped.";
            if (current.State == ServiceState.Blocked)
                return current.Text;

            try
            {
                ProcessStartInfo psi = new ProcessStartInfo(
                    Environment.GetEnvironmentVariable("ComSpec") ?? "cmd.exe",
                    "/d /s /c \"\"" + service.RunnerPath + "\"\"");
                psi.WorkingDirectory = service.ProjectPath;
                psi.UseShellExecute = false;
                psi.CreateNoWindow = true;
                psi.WindowStyle = ProcessWindowStyle.Hidden;

                Process process = Process.Start(psi);
                if (process == null) return service.DisplayName + ": start failed; no process returned.";
                WriteRecord(service, process);

                Stopwatch stopwatch = Stopwatch.StartNew();
                while (stopwatch.ElapsedMilliseconds < 20000)
                {
                    ServiceStatus status = GetServiceStatus(service);
                    if (status.State == ServiceState.Online)
                    {
                        process.Dispose();
                        return status.Text;
                    }
                    if (process.HasExited)
                    {
                        int code = process.ExitCode;
                        process.Dispose();
                        DeleteRecord(service);
                        return service.DisplayName + ": launcher exited before readiness (exit " + code + ").";
                    }
                    Thread.Sleep(250);
                }

                process.Dispose();
                return GetServiceStatus(service).Text;
            }
            catch (Exception ex)
            {
                return service.DisplayName + ": start failed: " + ex.Message;
            }
        }

        private static string StopService(ServiceDefinition service)
        {
            Process owned;
            if (!TryGetOwnedProcess(service, out owned))
            {
                int listener = GetListeningPid(service.Port);
                if (listener > 0)
                    return service.DisplayName + ": not stopped; port " + service.Port +
                        " belongs to unowned PID " + listener + ".";
                DeleteRecord(service);
                return service.DisplayName + " is already Offline.";
            }

            int rootPid = owned.Id;
            owned.Dispose();

            int listenerPid = GetListeningPid(service.Port);
            if (listenerPid > 0 && !IsDescendantOrSame(listenerPid, rootPid))
            {
                return service.DisplayName + ": stop refused; listener PID " + listenerPid +
                    " is not owned by tracked launcher PID " + rootPid + ".";
            }

            try
            {
                ProcessStartInfo psi = new ProcessStartInfo(
                    "taskkill.exe", "/PID " + rootPid + " /T /F");
                psi.UseShellExecute = false;
                psi.CreateNoWindow = true;
                using (Process killer = Process.Start(psi))
                {
                    if (killer != null) killer.WaitForExit(8000);
                }

                for (int i = 0; i < 40; i++)
                {
                    Process stillOwned;
                    if (!TryGetOwnedProcess(service, out stillOwned))
                    {
                        DeleteRecord(service);
                        int remaining = GetListeningPid(service.Port);
                        if (remaining == 0)
                            return service.DisplayName + " stopped.";
                        return service.DisplayName + ": owned process stopped, but port " +
                            service.Port + " is now occupied by PID " + remaining + ".";
                    }
                    if (stillOwned != null) stillOwned.Dispose();
                    Thread.Sleep(150);
                }

                return service.DisplayName + ": owned process did not stop within the safety window.";
            }
            catch (Exception ex)
            {
                return service.DisplayName + ": stop failed: " + ex.Message;
            }
        }

        public static string StartBackend()
        {
            string first = StartService(BackendHttp);
            string second = StartService(BackendVite);
            return first + Environment.NewLine + second;
        }

        public static string StopBackend()
        {
            string first = StopService(BackendVite);
            string second = StopService(BackendHttp);
            return first + Environment.NewLine + second;
        }

        public static string RestartBackend()
        {
            return StopBackend() + Environment.NewLine + StartBackend();
        }

        public static string StartWebsite()
        {
            return StartService(WebsiteNext);
        }

        public static string StopWebsite()
        {
            return StopService(WebsiteNext);
        }

        public static string RestartWebsite()
        {
            return StopWebsite() + Environment.NewLine + StartWebsite();
        }

        public static string StartAll()
        {
            return StartBackend() + Environment.NewLine + StartWebsite();
        }

        public static string StopAll()
        {
            return StopWebsite() + Environment.NewLine + StopBackend();
        }

        public static string BackendStatus()
        {
            ServiceStatus http = GetServiceStatus(BackendHttp);
            ServiceStatus vite = GetServiceStatus(BackendVite);

            if (http.State == ServiceState.Online && vite.State == ServiceState.Online)
                return "Backend / POS Online";
            if (http.State == ServiceState.Offline && vite.State == ServiceState.Offline)
                return "Backend / POS Offline";
            if (http.State == ServiceState.Blocked || vite.State == ServiceState.Blocked)
                return "Backend / POS Blocked | " + http.Text + " | " + vite.Text;
            return "Backend / POS Partial | " + http.Text + " | " + vite.Text;
        }

        public static string WebsiteStatus()
        {
            ServiceStatus status = GetServiceStatus(WebsiteNext);
            if (status.State == ServiceState.Online) return "Website Online";
            if (status.State == ServiceState.Offline) return "Website Offline";
            return "Website " + status.State + " | " + status.Text;
        }

        public static bool BackendOnline()
        {
            return GetServiceStatus(BackendHttp).State == ServiceState.Online &&
                   GetServiceStatus(BackendVite).State == ServiceState.Online;
        }

        public static bool WebsiteOnline()
        {
            return GetServiceStatus(WebsiteNext).State == ServiceState.Online;
        }

        public static string StatusAll()
        {
            return BackendStatus() + Environment.NewLine + WebsiteStatus();
        }

        [DllImport("user32.dll")]
        private static extern bool SetForegroundWindow(IntPtr hWnd);

        [DllImport("user32.dll")]
        private static extern bool ShowWindow(IntPtr hWnd, int nCmdShow);

        private static bool TitleMatches(string title, string[] markers)
        {
            if (string.IsNullOrEmpty(title)) return false;
            foreach (string marker in markers)
                if (title.IndexOf(marker, StringComparison.OrdinalIgnoreCase) >= 0)
                    return true;
            return false;
        }

        private static string FindEdgeExecutable()
        {
            string onPath = FindOnPath("msedge.exe");
            if (!string.IsNullOrEmpty(onPath)) return onPath;
            string[] candidates = new[]
            {
                @"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
                @"C:\Program Files\Microsoft\Edge\Application\msedge.exe"
            };
            foreach (string candidate in candidates)
                if (File.Exists(candidate)) return candidate;
            return null;
        }

        private static string BrowserGuardPath(string key)
        {
            return Path.Combine(StateRoot, "browser-" + key + ".stamp");
        }

        private static void MarkBrowserOpen(string key)
        {
            try { File.WriteAllText(BrowserGuardPath(key), DateTime.UtcNow.Ticks.ToString()); }
            catch { }
        }

        private static bool TryFocusRecentEdge(string key)
        {
            try
            {
                string path = BrowserGuardPath(key);
                if (!File.Exists(path)) return false;
                long ticks;
                if (!long.TryParse(File.ReadAllText(path).Trim(), out ticks)) return false;
                DateTime opened = new DateTime(ticks, DateTimeKind.Utc);
                if (DateTime.UtcNow - opened > TimeSpan.FromSeconds(10)) return false;

                Process window = Process.GetProcessesByName("msedge")
                    .FirstOrDefault(p => p.MainWindowHandle != IntPtr.Zero);
                if (window == null) return false;
                ShowWindow(window.MainWindowHandle, 9);
                SetForegroundWindow(window.MainWindowHandle);
                return true;
            }
            catch { return false; }
        }

        private static string SmartOpenEdge(string target, string label, string browserKey, string[] titleMarkers)
        {
            try
            {
                Process[] windows = Process.GetProcessesByName("msedge")
                    .Where(p => p.MainWindowHandle != IntPtr.Zero).ToArray();
                foreach (Process process in windows)
                {
                    process.Refresh();
                    string original = process.MainWindowTitle;
                    ShowWindow(process.MainWindowHandle, 9);
                    SetForegroundWindow(process.MainWindowHandle);
                    Thread.Sleep(150);
                    process.Refresh();

                    if (TitleMatches(process.MainWindowTitle, titleMarkers))
                        return label + ": existing Edge tab focused.";

                    for (int i = 0; i < 40; i++)
                    {
                        SendKeys.SendWait("^{PGDN}");
                        Thread.Sleep(90);
                        process.Refresh();
                        string title = process.MainWindowTitle;
                        if (TitleMatches(title, titleMarkers))
                            return label + ": existing Edge tab focused.";
                        if (i > 0 && string.Equals(title, original, StringComparison.Ordinal)) break;
                    }
                }
            }
            catch { }

            if (TryFocusRecentEdge(browserKey))
                return label + ": recent Edge target focused; duplicate open skipped.";

            try
            {
                string edge = FindEdgeExecutable();
                if (!string.IsNullOrEmpty(edge))
                {
                    Process.Start(new ProcessStartInfo(edge, target) { UseShellExecute = true });
                    MarkBrowserOpen(browserKey);
                    return label + ": opened in Edge.";
                }

                Process.Start(new ProcessStartInfo(target) { UseShellExecute = true });
                MarkBrowserOpen(browserKey);
                return label + ": Edge unavailable; opened in the default browser.";
            }
            catch (Exception ex)
            {
                return label + ": browser open failed: " + ex.Message;
            }
        }

        public static string OpenBackend()
        {
            if (!BackendOnline()) return "Backend / POS is not Online; browser was not opened.";
            return SmartOpenEdge(
                "http://127.0.0.1:18080/internal/admin/pos",
                "Backend / POS",
                "backend",
                new[] { "mobiST POS", "Team Member sign in", "POS home" });
        }

        public static string OpenWebsite()
        {
            if (!WebsiteOnline()) return "Website is not Online; browser was not opened.";
            return SmartOpenEdge(
                "http://127.0.0.1:13000/",
                "Website",
                "website",
                new[] { "mobiST Technologies" });
        }

        public static void OpenFolder(string path)
        {
            try
            {
                Process.Start(new ProcessStartInfo("explorer.exe", "\"" + path + "\"")
                {
                    UseShellExecute = true
                });
            }
            catch { }
        }

        public static string ControlSafetySummary()
        {
            return "Tracked ownership = PID + process start time + exact runner path; ports alone never authorize termination.";
        }
    }
}
