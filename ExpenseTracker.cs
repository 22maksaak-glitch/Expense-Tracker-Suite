// ExpenseTracker.cs - Трекер расходов на C# (CLI + WinForms)
using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Text.Json;
using System.Windows.Forms;

namespace ExpenseTracker
{
    public class Expense
    {
        public int Id { get; set; }
        public string Category { get; set; }
        public double Amount { get; set; }
        public string Date { get; set; }
        public string Description { get; set; }
    }

    public class Tracker
    {
        public List<Expense> Expenses { get; set; } = new List<Expense>();
        public int NextId { get; set; } = 1;
        private const string DataFile = "expenses.json";

        public void Load()
        {
            if (File.Exists(DataFile))
            {
                try
                {
                    string json = File.ReadAllText(DataFile);
                    var data = JsonSerializer.Deserialize<Tracker>(json);
                    if (data != null)
                    {
                        Expenses = data.Expenses;
                        NextId = data.NextId;
                        return;
                    }
                }
                catch { }
            }
            Expenses = new List<Expense>();
            NextId = 1;
        }

        public void Save()
        {
            string json = JsonSerializer.Serialize(this, new JsonSerializerOptions { WriteIndented = true });
            File.WriteAllText(DataFile, json);
        }

        public Expense AddExpense(string category, double amount, string date, string description)
        {
            var exp = new Expense { Id = NextId++, Category = category, Amount = amount, Date = date, Description = description };
            Expenses.Add(exp);
            Save();
            return exp;
        }

        public bool EditExpense(int id, string category, double? amount, string date, string description)
        {
            var exp = Expenses.FirstOrDefault(e => e.Id == id);
            if (exp == null) return false;
            if (category != null) exp.Category = category;
            if (amount.HasValue) exp.Amount = amount.Value;
            if (date != null) exp.Date = date;
            if (description != null) exp.Description = description;
            Save();
            return true;
        }

        public bool DeleteExpense(int id)
        {
            int removed = Expenses.RemoveAll(e => e.Id == id);
            if (removed > 0) { Save(); return true; }
            return false;
        }

        public List<Expense> GetExpenses(string category, string dateFrom, string dateTo, double? minAmount, double? maxAmount)
        {
            var query = Expenses.AsEnumerable();
            if (!string.IsNullOrEmpty(category)) query = query.Where(e => e.Category == category);
            if (!string.IsNullOrEmpty(dateFrom)) query = query.Where(e => e.Date.CompareTo(dateFrom) >= 0);
            if (!string.IsNullOrEmpty(dateTo)) query = query.Where(e => e.Date.CompareTo(dateTo) <= 0);
            if (minAmount.HasValue) query = query.Where(e => e.Amount >= minAmount.Value);
            if (maxAmount.HasValue) query = query.Where(e => e.Amount <= maxAmount.Value);
            return query.OrderBy(e => e.Date).ToList();
        }

        public (double total, int count, Dictionary<string, double> byCategory, double average) GetStatistics(string category, string dateFrom, string dateTo)
        {
            var list = GetExpenses(category, dateFrom, dateTo, null, null);
            double total = list.Sum(e => e.Amount);
            var byCat = list.GroupBy(e => e.Category).ToDictionary(g => g.Key, g => g.Sum(e => e.Amount));
            double avg = list.Count > 0 ? total / list.Count : 0;
            return (total, list.Count, byCat, avg);
        }

        public List<string> GetCategories() => Expenses.Select(e => e.Category).Distinct().OrderBy(c => c).ToList();
    }

    class Program
    {
        [STAThread]
        static void Main(string[] args)
        {
            if (args.Length > 0 && args[0] == "--gui")
            {
                Application.EnableVisualStyles();
                Application.Run(new TrackerGUI());
                return;
            }
            // CLI
            var tracker = new Tracker();
            tracker.Load();
            InteractiveMode(tracker);
        }

        static void InteractiveMode(Tracker tracker)
        {
            while (true)
            {
                Console.WriteLine("\n💰 Трекер расходов (интерактивный)");
                Console.WriteLine("1. Добавить расход");
                Console.WriteLine("2. Показать расходы (с фильтром)");
                Console.WriteLine("3. Статистика");
                Console.WriteLine("4. Редактировать");
                Console.WriteLine("5. Удалить");
                Console.WriteLine("6. Список категорий");
                Console.WriteLine("0. Выход");
                Console.Write("Выберите действие: ");
                string choice = Console.ReadLine();
                switch (choice)
                {
                    case "0": return;
                    case "1":
                        Console.Write("Категория: ");
                        string cat = Console.ReadLine();
                        if (string.IsNullOrEmpty(cat)) { Console.WriteLine("Категория обязательна"); break; }
                        Console.Write("Сумма: ");
                        if (!double.TryParse(Console.ReadLine(), out double amt) || amt <= 0) { Console.WriteLine("Неверная сумма"); break; }
                        Console.Write("Дата (ГГГГ-ММ-ДД, Enter сегодня): ");
                        string date = Console.ReadLine();
                        if (string.IsNullOrEmpty(date)) date = DateTime.Now.ToString("yyyy-MM-dd");
                        Console.Write("Описание (необязательно): ");
                        string desc = Console.ReadLine();
                        var exp = tracker.AddExpense(cat, amt, date, desc);
                        Console.WriteLine($"✅ Добавлен расход #{exp.Id}");
                        break;
                    case "2":
                        Console.Write("Категория (Enter пропустить): ");
                        string filterCat = Console.ReadLine();
                        if (string.IsNullOrEmpty(filterCat)) filterCat = null;
                        Console.Write("Дата от (Enter пропустить): ");
                        string from = Console.ReadLine();
                        if (string.IsNullOrEmpty(from)) from = null;
                        Console.Write("Дата до (Enter пропустить): ");
                        string to = Console.ReadLine();
                        if (string.IsNullOrEmpty(to)) to = null;
                        var list = tracker.GetExpenses(filterCat, from, to, null, null);
                        if (!list.Any()) Console.WriteLine("Нет записей.");
                        else
                        {
                            double total = list.Sum(e => e.Amount);
                            Console.WriteLine($"{"ID",-4} {"Дата",-12} {"Категория",-15} {"Сумма",-10} Описание");
                            foreach (var e in list)
                                Console.WriteLine($"{e.Id,-4} {e.Date,-12} {e.Category,-15} {e.Amount,-10:F2} {e.Description}");
                            Console.WriteLine($"\nИтого: {total:F2}");
                        }
                        break;
                    case "3":
                        Console.Write("Категория (Enter пропустить): ");
                        string statCat = Console.ReadLine();
                        if (string.IsNullOrEmpty(statCat)) statCat = null;
                        Console.Write("Дата от (Enter пропустить): ");
                        string statFrom = Console.ReadLine();
                        if (string.IsNullOrEmpty(statFrom)) statFrom = null;
                        Console.Write("Дата до (Enter пропустить): ");
                        string statTo = Console.ReadLine();
                        if (string.IsNullOrEmpty(statTo)) statTo = null;
                        var stats = tracker.GetStatistics(statCat, statFrom, statTo);
                        Console.WriteLine("📊 Статистика");
                        Console.WriteLine($"Всего записей: {stats.count}");
                        Console.WriteLine($"Общая сумма: {stats.total:F2}");
                        Console.WriteLine($"Средний расход: {stats.average:F2}");
                        if (stats.byCategory.Any())
                        {
                            Console.WriteLine("По категориям:");
                            var sorted = stats.byCategory.OrderByDescending(kv => kv.Value);
                            foreach (var kv in sorted)
                            {
                                double percent = stats.total > 0 ? kv.Value / stats.total * 100 : 0;
                                Console.WriteLine($"  {kv.Key}: {kv.Value:F2} ({percent:F1}%)");
                            }
                        }
                        break;
                    case "4":
                        Console.Write("ID расхода: ");
                        if (!int.TryParse(Console.ReadLine(), out int editId)) { Console.WriteLine("Неверный ID"); break; }
                        var editExp = tracker.Expenses.FirstOrDefault(e => e.Id == editId);
                        if (editExp == null) { Console.WriteLine("Не найдено"); break; }
                        Console.WriteLine("Оставьте пустым, чтобы не менять.");
                        Console.Write($"Категория ({editExp.Category}): ");
                        string newCat = Console.ReadLine();
                        if (string.IsNullOrEmpty(newCat)) newCat = null;
                        Console.Write($"Сумма ({editExp.Amount:F2}): ");
                        string amtStr = Console.ReadLine();
                        double? newAmt = null;
                        if (!string.IsNullOrEmpty(amtStr) && double.TryParse(amtStr, out double amtVal)) newAmt = amtVal;
                        Console.Write($"Дата ({editExp.Date}): ");
                        string newDate = Console.ReadLine();
                        if (string.IsNullOrEmpty(newDate)) newDate = null;
                        Console.Write($"Описание ({editExp.Description}): ");
                        string newDesc = Console.ReadLine();
                        if (string.IsNullOrEmpty(newDesc)) newDesc = null;
                        if (tracker.EditExpense(editId, newCat, newAmt, newDate, newDesc))
                            Console.WriteLine("✅ Обновлено");
                        else
                            Console.WriteLine("❌ Ошибка обновления");
                        break;
                    case "5":
                        Console.Write("ID для удаления: ");
                        if (!int.TryParse(Console.ReadLine(), out int delId)) { Console.WriteLine("Неверный ID"); break; }
                        if (tracker.DeleteExpense(delId))
                            Console.WriteLine("✅ Удалено");
                        else
                            Console.WriteLine("❌ Не найдено");
                        break;
                    case "6":
                        var cats = tracker.GetCategories();
                        if (cats.Any())
                            Console.WriteLine("Категории: " + string.Join(", ", cats));
                        else
                            Console.WriteLine("Нет категорий");
                        break;
                    default:
                        Console.WriteLine("Неверный выбор");
                        break;
                }
            }
        }
    }

    // ========== GUI ==========
    public class TrackerGUI : Form
    {
        private Tracker tracker = new Tracker();
        private DataGridView grid;
        private TextBox catBox, amountBox, dateBox, descBox;
        private ComboBox filterCat;
        private TextBox filterFrom, filterTo;
        private Label totalLabel;

        public TrackerGUI()
        {
            tracker.Load();
            Text = "💰 Трекер расходов";
            Size = new System.Drawing.Size(850, 600);
            StartPosition = FormStartPosition.CenterScreen;
            InitUI();
            RefreshGrid();
            UpdateCategoryFilter();
        }

        private void InitUI()
        {
            var topPanel = new TableLayoutPanel { Dock = DockStyle.Top, ColumnCount = 9, Padding = new Padding(5), AutoSize = true };
            topPanel.Controls.Add(new Label { Text = "Категория:", AutoSize = true }, 0, 0);
            catBox = new TextBox { Width = 100 };
            topPanel.Controls.Add(catBox, 1, 0);
            topPanel.Controls.Add(new Label { Text = "Сумма:", AutoSize = true }, 2, 0);
            amountBox = new TextBox { Width = 80 };
            topPanel.Controls.Add(amountBox, 3, 0);
            topPanel.Controls.Add(new Label { Text = "Дата:", AutoSize = true }, 4, 0);
            dateBox = new TextBox { Width = 100, Text = DateTime.Now.ToString("yyyy-MM-dd") };
            topPanel.Controls.Add(dateBox, 5, 0);
            topPanel.Controls.Add(new Label { Text = "Описание:", AutoSize = true }, 6, 0);
            descBox = new TextBox { Width = 150 };
            topPanel.Controls.Add(descBox, 7, 0);
            var addBtn = new Button { Text = "➕ Добавить" };
            addBtn.Click += (s, e) => AddExpense();
            topPanel.Controls.Add(addBtn, 8, 0);

            var filterPanel = new TableLayoutPanel { Dock = DockStyle.Top, ColumnCount = 7, Padding = new Padding(5), AutoSize = true };
            filterPanel.Controls.Add(new Label { Text = "Фильтр категория:", AutoSize = true }, 0, 0);
            filterCat = new ComboBox { DropDownStyle = ComboBoxStyle.DropDownList, Width = 120 };
            filterCat.SelectedIndexChanged += (s, e) => RefreshGrid();
            filterPanel.Controls.Add(filterCat, 1, 0);
            filterPanel.Controls.Add(new Label { Text = "Дата от:", AutoSize = true }, 2, 0);
            filterFrom = new TextBox { Width = 100 };
            filterPanel.Controls.Add(filterFrom, 3, 0);
            filterPanel.Controls.Add(new Label { Text = "до:", AutoSize = true }, 4, 0);
            filterTo = new TextBox { Width = 100 };
            filterPanel.Controls.Add(filterTo, 5, 0);
            var filterBtn = new Button { Text = "Применить" };
            filterBtn.Click += (s, e) => RefreshGrid();
            filterPanel.Controls.Add(filterBtn, 6, 0);

            Controls.Add(filterPanel);
            Controls.Add(topPanel);

            grid = new DataGridView { Dock = DockStyle.Fill, AllowUserToAddRows = false, ReadOnly = true, AutoSizeColumnsMode = DataGridViewAutoSizeColumnsMode.Fill };
            grid.Columns.Add("Id", "ID");
            grid.Columns.Add("Date", "Дата");
            grid.Columns.Add("Category", "Категория");
            grid.Columns.Add("Amount", "Сумма");
            grid.Columns.Add("Description", "Описание");
            Controls.Add(grid);

            var bottom = new FlowLayoutPanel { Dock = DockStyle.Bottom, Padding = new Padding(5), Height = 40 };
            var editBtn = new Button { Text = "✏️ Редактировать" };
            editBtn.Click += (s, e) => EditExpense();
            bottom.Controls.Add(editBtn);
            var delBtn = new Button { Text = "🗑 Удалить" };
            delBtn.Click += (s, e) => DeleteExpense();
            bottom.Controls.Add(delBtn);
            var statsBtn = new Button { Text = "📊 Статистика" };
            statsBtn.Click += (s, e) => ShowStats();
            bottom.Controls.Add(statsBtn);
            var exportBtn = new Button { Text = "💾 Экспорт CSV" };
            exportBtn.Click += (s, e) => ExportCSV();
            bottom.Controls.Add(exportBtn);
            bottom.Controls.Add(new Label { Text = "Итого:", AutoSize = true });
            totalLabel = new Label { Text = "0.00", AutoSize = true };
            bottom.Controls.Add(totalLabel);
            Controls.Add(bottom);
        }

        private void UpdateCategoryFilter()
        {
            filterCat.Items.Clear();
            filterCat.Items.Add("");
            foreach (var c in tracker.GetCategories()) filterCat.Items.Add(c);
            filterCat.SelectedIndex = 0;
        }

        private void RefreshGrid()
        {
            grid.Rows.Clear();
            string cat = filterCat.SelectedItem?.ToString();
            if (cat == "") cat = null;
            string from = filterFrom.Text.Trim();
            if (from == "") from = null;
            string to = filterTo.Text.Trim();
            if (to == "") to = null;
            var list = tracker.GetExpenses(cat, from, to, null, null);
            double total = 0;
            foreach (var e in list)
            {
                grid.Rows.Add(e.Id, e.Date, e.Category, e.Amount, e.Description);
                total += e.Amount;
            }
            totalLabel.Text = total.ToString("F2");
        }

        private void AddExpense()
        {
            string cat = catBox.Text.Trim();
            if (string.IsNullOrEmpty(cat)) { MessageBox.Show("Введите категорию"); return; }
            if (!double.TryParse(amountBox.Text.Trim(), out double amt) || amt <= 0) { MessageBox.Show("Неверная сумма"); return; }
            string date = dateBox.Text.Trim();
            if (string.IsNullOrEmpty(date)) date = DateTime.Now.ToString("yyyy-MM-dd");
            string desc = descBox.Text.Trim();
            tracker.AddExpense(cat, amt, date, desc);
            catBox.Text = "";
            amountBox.Text = "";
            descBox.Text = "";
            UpdateCategoryFilter();
            RefreshGrid();
        }

        private void EditExpense()
        {
            if (grid.SelectedRows.Count == 0) { MessageBox.Show("Выберите запись"); return; }
            int id = (int)grid.SelectedRows[0].Cells[0].Value;
            var exp = tracker.Expenses.FirstOrDefault(e => e.Id == id);
            if (exp == null) return;
            var dialog = new Form { Text = "Редактировать", Size = new System.Drawing.Size(400, 250), StartPosition = FormStartPosition.CenterParent };
            var layout = new TableLayoutPanel { Dock = DockStyle.Fill, ColumnCount = 2, Padding = new Padding(10) };
            layout.RowStyles.Add(new RowStyle(SizeType.AutoSize));
            layout.RowStyles.Add(new RowStyle(SizeType.AutoSize));
            layout.RowStyles.Add(new RowStyle(SizeType.AutoSize));
            layout.RowStyles.Add(new RowStyle(SizeType.AutoSize));
            layout.RowStyles.Add(new RowStyle(SizeType.Percent, 100));
            layout.Controls.Add(new Label { Text = "Категория:", AutoSize = true }, 0, 0);
            var catEdit = new TextBox { Text = exp.Category, Width = 200 };
            layout.Controls.Add(catEdit, 1, 0);
            layout.Controls.Add(new Label { Text = "Сумма:", AutoSize = true }, 0, 1);
            var amtEdit = new TextBox { Text = exp.Amount.ToString(), Width = 100 };
            layout.Controls.Add(amtEdit, 1, 1);
            layout.Controls.Add(new Label { Text = "Дата:", AutoSize = true }, 0, 2);
            var dateEdit = new TextBox { Text = exp.Date, Width = 120 };
            layout.Controls.Add(dateEdit, 1, 2);
            layout.Controls.Add(new Label { Text = "Описание:", AutoSize = true }, 0, 3);
            var descEdit = new TextBox { Text = exp.Description, Width = 200 };
            layout.Controls.Add(descEdit, 1, 3);
            var saveBtn = new Button { Text = "Сохранить", Dock = DockStyle.Right };
            saveBtn.Click += (sender, e) =>
            {
                string cat = catEdit.Text.Trim();
                if (string.IsNullOrEmpty(cat)) cat = null;
                double? amt = null;
                if (double.TryParse(amtEdit.Text.Trim(), out double a)) amt = a;
                string date = dateEdit.Text.Trim();
                if (string.IsNullOrEmpty(date)) date = null;
                string desc = descEdit.Text.Trim();
                if (string.IsNullOrEmpty(desc)) desc = null;
                if (tracker.EditExpense(id, cat, amt, date, desc))
                {
                    UpdateCategoryFilter();
                    RefreshGrid();
                    dialog.Close();
                }
                else MessageBox.Show("Ошибка обновления");
            };
            layout.Controls.Add(saveBtn, 1, 4);
            dialog.Controls.Add(layout);
            dialog.ShowDialog();
        }

        private void DeleteExpense()
        {
            if (grid.SelectedRows.Count == 0) { MessageBox.Show("Выберите запись"); return; }
            int id = (int)grid.SelectedRows[0].Cells[0].Value;
            if (MessageBox.Show($"Удалить расход #{id}?", "Удаление", MessageBoxButtons.YesNo) == DialogResult.Yes)
            {
                if (tracker.DeleteExpense(id))
                {
                    UpdateCategoryFilter();
                    RefreshGrid();
                }
            }
        }

        private void ShowStats()
        {
            var stats = tracker.GetStatistics(null, null, null);
            var sb = new System.Text.StringBuilder();
            sb.AppendLine("📊 Статистика");
            sb.AppendLine($"Всего записей: {stats.count}");
            sb.AppendLine($"Общая сумма: {stats.total:F2}");
            sb.AppendLine($"Средний расход: {stats.average:F2}");
            if (stats.byCategory.Any())
            {
                sb.AppendLine("По категориям:");
                var sorted = stats.byCategory.OrderByDescending(kv => kv.Value);
                foreach (var kv in sorted)
                {
                    double percent = stats.total > 0 ? kv.Value / stats.total * 100 : 0;
                    sb.AppendLine($"  {kv.Key}: {kv.Value:F2} ({percent:F1}%)");
                }
            }
            MessageBox.Show(sb.ToString(), "Статистика", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }

        private void ExportCSV()
        {
            var sfd = new SaveFileDialog { Filter = "CSV files|*.csv", DefaultExt = "csv" };
            if (sfd.ShowDialog() == DialogResult.OK)
            {
                using (var sw = new StreamWriter(sfd.FileName))
                {
                    sw.WriteLine("ID,Дата,Категория,Сумма,Описание");
                    foreach (var e in tracker.Expenses)
                        sw.WriteLine($"{e.Id},{e.Date},{e.Category},{e.Amount},{e.Description}");
                }
                MessageBox.Show("Экспортировано в " + sfd.FileName);
            }
        }
    }
}
