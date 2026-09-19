using System;
using System.Drawing;
using System.IO;
using System.Threading;
using System.Windows.Forms;

namespace MobiSTControl
{
    internal sealed class MainForm : Form
    {
        private readonly Label backendStatus = new Label();
        private readonly Label websiteStatus = new Label();
        private readonly TextBox log = new TextBox();
        private readonly Button startAll = new Button();
        private readonly Button stopAll = new Button();
        private readonly System.Windows.Forms.Timer timer = new System.Windows.Forms.Timer();

        public MainForm()
        {
            Text = "mobiST Control";
            StartPosition = FormStartPosition.CenterScreen;
            MinimumSize = new Size(820, 560);
            ClientSize = new Size(930, 620);
            Font = new Font("Segoe UI", 9F);
            BackColor = Color.FromArgb(247, 248, 251);

            try
            {
                string iconPath = Path.Combine(ControlOps.BackendProject, "public", "favicon.ico");
                if (File.Exists(iconPath)) Icon = new Icon(iconPath);
            }
            catch { }

            BuildUi();
            RefreshStatus();

            timer.Interval = 2000;
            timer.Tick += delegate { RefreshStatus(); };
            timer.Start();
        }

        protected override void Dispose(bool disposing)
        {
            if (disposing) timer.Dispose();
            base.Dispose(disposing);
        }

        private void BuildUi()
        {
            TableLayoutPanel root = new TableLayoutPanel();
            root.Dock = DockStyle.Fill;
            root.Padding = new Padding(22);
            root.ColumnCount = 1;
            root.RowCount = 4;
            root.RowStyles.Add(new RowStyle(SizeType.Absolute, 92));
            root.RowStyles.Add(new RowStyle(SizeType.Percent, 45));
            root.RowStyles.Add(new RowStyle(SizeType.Absolute, 62));
            root.RowStyles.Add(new RowStyle(SizeType.Percent, 55));
            Controls.Add(root);

            Panel header = new Panel();
            header.Dock = DockStyle.Fill;
            PictureBox logo = new PictureBox();
            logo.SizeMode = PictureBoxSizeMode.Zoom;
            logo.Bounds = new Rectangle(0, 8, 260, 64);
            try
            {
                string logoPath = Path.Combine(
                    ControlOps.BackendProject, "public", "brand", "mobist-wordmark-print.png");
                if (File.Exists(logoPath)) logo.Image = Image.FromFile(logoPath);
            }
            catch { }
            header.Controls.Add(logo);

            Label title = new Label();
            title.Text = "Local Development Control";
            title.AutoSize = true;
            title.Font = new Font("Segoe UI", 16F, FontStyle.Bold);
            title.ForeColor = Color.FromArgb(17, 24, 39);
            title.Location = new Point(290, 18);
            header.Controls.Add(title);

            Label subtitle = new Label();
            subtitle.Text = @"C:\mobisttech · owned processes only";
            subtitle.AutoSize = true;
            subtitle.ForeColor = Color.FromArgb(102, 112, 133);
            subtitle.Location = new Point(292, 52);
            header.Controls.Add(subtitle);
            root.Controls.Add(header, 0, 0);

            TableLayoutPanel cards = new TableLayoutPanel();
            cards.Dock = DockStyle.Fill;
            cards.ColumnCount = 2;
            cards.RowCount = 1;
            cards.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50));
            cards.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50));
            root.Controls.Add(cards, 0, 1);

            cards.Controls.Add(BuildTargetCard(
                "Backend / POS",
                @"C:\mobisttech\backend",
                "Laravel 18080 + Vite 15173",
                backendStatus,
                delegate { RunAsync("Start Backend", ControlOps.StartBackend); },
                delegate { RunAsync("Stop Backend", ControlOps.StopBackend); },
                delegate { RunAsync("Restart Backend", ControlOps.RestartBackend); },
                delegate { RunAsync("Open Backend", ControlOps.OpenBackend); },
                delegate { ControlOps.OpenFolder(ControlOps.BackendProject); }),
                0, 0);

            cards.Controls.Add(BuildTargetCard(
                "Website",
                @"C:\mobisttech\website",
                "Next.js development server 13000",
                websiteStatus,
                delegate { RunAsync("Start Website", ControlOps.StartWebsite); },
                delegate { RunAsync("Stop Website", ControlOps.StopWebsite); },
                delegate { RunAsync("Restart Website", ControlOps.RestartWebsite); },
                delegate { RunAsync("Open Website", ControlOps.OpenWebsite); },
                delegate { ControlOps.OpenFolder(ControlOps.WebsiteProject); }),
                1, 0);

            FlowLayoutPanel allActions = new FlowLayoutPanel();
            allActions.Dock = DockStyle.Fill;
            allActions.FlowDirection = FlowDirection.LeftToRight;
            allActions.WrapContents = false;
            allActions.Padding = new Padding(0, 12, 0, 8);

            startAll.Text = "Start All";
            startAll.AutoSize = true;
            startAll.Padding = new Padding(18, 7, 18, 7);
            startAll.BackColor = Color.FromArgb(0, 128, 128);
            startAll.ForeColor = Color.White;
            startAll.FlatStyle = FlatStyle.Flat;
            startAll.Click += delegate { RunAsync("Start All", ControlOps.StartAll); };

            stopAll.Text = "Stop All";
            stopAll.AutoSize = true;
            stopAll.Padding = new Padding(18, 7, 18, 7);
            stopAll.FlatStyle = FlatStyle.Flat;
            stopAll.Click += delegate { RunAsync("Stop All", ControlOps.StopAll); };

            Button refresh = new Button();
            refresh.Text = "Refresh Status";
            refresh.AutoSize = true;
            refresh.Padding = new Padding(14, 7, 14, 7);
            refresh.Click += delegate { RefreshStatus(); };

            allActions.Controls.Add(startAll);
            allActions.Controls.Add(stopAll);
            allActions.Controls.Add(refresh);

            Label safety = new Label();
            safety.AutoSize = true;
            safety.Padding = new Padding(15, 10, 0, 0);
            safety.ForeColor = Color.FromArgb(102, 112, 133);
            safety.Text = "Stop never authorizes termination from a port number alone.";
            allActions.Controls.Add(safety);
            root.Controls.Add(allActions, 0, 2);

            log.Multiline = true;
            log.ReadOnly = true;
            log.ScrollBars = ScrollBars.Vertical;
            log.Dock = DockStyle.Fill;
            log.BackColor = Color.White;
            log.BorderStyle = BorderStyle.FixedSingle;
            root.Controls.Add(log, 0, 3);
            Log(ControlOps.ControlSafetySummary());
        }

        private Control BuildTargetCard(
            string title,
            string path,
            string detail,
            Label status,
            Action start,
            Action stop,
            Action restart,
            Action open,
            Action folder)
        {
            Panel card = new Panel();
            card.Dock = DockStyle.Fill;
            card.Margin = new Padding(6);
            card.Padding = new Padding(18);
            card.BackColor = Color.White;
            card.BorderStyle = BorderStyle.FixedSingle;

            Label heading = new Label();
            heading.Text = title;
            heading.Font = new Font("Segoe UI", 15F, FontStyle.Bold);
            heading.AutoSize = true;
            heading.Location = new Point(18, 16);
            card.Controls.Add(heading);

            Label pathLabel = new Label();
            pathLabel.Text = path;
            pathLabel.AutoSize = true;
            pathLabel.ForeColor = Color.FromArgb(102, 112, 133);
            pathLabel.Location = new Point(20, 52);
            card.Controls.Add(pathLabel);

            Label detailLabel = new Label();
            detailLabel.Text = detail;
            detailLabel.AutoSize = true;
            detailLabel.ForeColor = Color.FromArgb(102, 112, 133);
            detailLabel.Location = new Point(20, 75);
            card.Controls.Add(detailLabel);

            status.AutoSize = false;
            status.Bounds = new Rectangle(20, 105, 390, 48);
            status.Font = new Font("Segoe UI", 10F, FontStyle.Bold);
            status.TextAlign = ContentAlignment.MiddleLeft;
            card.Controls.Add(status);

            FlowLayoutPanel buttons = new FlowLayoutPanel();
            buttons.Bounds = new Rectangle(16, 165, 400, 80);
            buttons.FlowDirection = FlowDirection.LeftToRight;
            buttons.WrapContents = true;

            buttons.Controls.Add(MakeButton("Start", start));
            buttons.Controls.Add(MakeButton("Stop", stop));
            buttons.Controls.Add(MakeButton("Restart", restart));
            buttons.Controls.Add(MakeButton("Open", open));
            buttons.Controls.Add(MakeButton("Open Folder", folder));
            card.Controls.Add(buttons);

            return card;
        }

        private Button MakeButton(string text, Action action)
        {
            Button button = new Button();
            button.Text = text;
            button.AutoSize = true;
            button.Padding = new Padding(9, 5, 9, 5);
            button.Margin = new Padding(4);
            button.FlatStyle = FlatStyle.System;
            button.Click += delegate { action(); };
            return button;
        }

        private void RunAsync(string label, Func<string> operation)
        {
            SetActionButtons(false);
            Log(label + " requested.");
            ThreadPool.QueueUserWorkItem(delegate
            {
                string result;
                try { result = operation(); }
                catch (Exception ex) { result = label + " failed: " + ex.Message; }

                if (!IsDisposed)
                {
                    BeginInvoke((MethodInvoker)delegate
                    {
                        Log(result);
                        RefreshStatus();
                        SetActionButtons(true);
                    });
                }
            });
        }

        private void SetActionButtons(bool enabled)
        {
            startAll.Enabled = enabled;
            stopAll.Enabled = enabled;
        }

        private void RefreshStatus()
        {
            string backend = ControlOps.BackendStatus();
            string website = ControlOps.WebsiteStatus();

            backendStatus.Text = backend;
            websiteStatus.Text = website;

            backendStatus.ForeColor = StatusColor(backend);
            websiteStatus.ForeColor = StatusColor(website);
        }

        private Color StatusColor(string text)
        {
            if (text.IndexOf("Online", StringComparison.OrdinalIgnoreCase) >= 0 &&
                text.IndexOf("Partial", StringComparison.OrdinalIgnoreCase) < 0)
                return Color.FromArgb(19, 138, 82);
            if (text.IndexOf("Blocked", StringComparison.OrdinalIgnoreCase) >= 0 ||
                text.IndexOf("Partial", StringComparison.OrdinalIgnoreCase) >= 0)
                return Color.FromArgb(180, 83, 9);
            return Color.FromArgb(102, 112, 133);
        }

        private void Log(string text)
        {
            string stamp = DateTime.Now.ToString("HH:mm:ss");
            log.AppendText("[" + stamp + "] " + text + Environment.NewLine);
        }
    }
}
