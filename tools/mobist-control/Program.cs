using System;
using System.Windows.Forms;

namespace MobiSTControl
{
    internal static class Program
    {
        [STAThread]
        private static void Main(string[] args)
        {
            if (args != null && args.Length > 0)
            {
                Environment.ExitCode = RunCommand(args);
                return;
            }

            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            Application.Run(new MainForm());
        }

        private static int RunCommand(string[] args)
        {
            try
            {
                string command = args[0].ToLowerInvariant();
                string result;

                switch (command)
                {
                    case "--start-backend":
                    case "--start-pos":
                        result = ControlOps.StartBackend();
                        break;
                    case "--stop-backend":
                    case "--stop-pos":
                        result = ControlOps.StopBackend();
                        break;
                    case "--restart-backend":
                    case "--restart-pos":
                        result = ControlOps.RestartBackend();
                        break;
                    case "--status-backend":
                    case "--status-pos":
                        result = ControlOps.BackendStatus();
                        break;
                    case "--open-backend":
                    case "--open-pos":
                        result = ControlOps.OpenBackend();
                        break;
                    case "--start-website":
                        result = ControlOps.StartWebsite();
                        break;
                    case "--stop-website":
                        result = ControlOps.StopWebsite();
                        break;
                    case "--restart-website":
                        result = ControlOps.RestartWebsite();
                        break;
                    case "--status-website":
                        result = ControlOps.WebsiteStatus();
                        break;
                    case "--open-website":
                        result = ControlOps.OpenWebsite();
                        break;
                    case "--start-all":
                        result = ControlOps.StartAll();
                        break;
                    case "--stop-all":
                        result = ControlOps.StopAll();
                        break;
                    case "--status-all":
                        result = ControlOps.StatusAll();
                        break;
                    case "--safety":
                        result = ControlOps.ControlSafetySummary();
                        break;
                    default:
                        Console.Error.WriteLine("Unknown command: " + args[0]);
                        return 2;
                }

                Console.WriteLine(result);
                return result.IndexOf("failed", StringComparison.OrdinalIgnoreCase) >= 0 ? 1 : 0;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine(ex.ToString());
                return 1;
            }
        }
    }
}
