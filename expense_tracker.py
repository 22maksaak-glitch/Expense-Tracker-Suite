#!/usr/bin/env python3
"""
expense_tracker.py - Трекер расходов на Python (CLI + Tkinter GUI)
Поддерживает: CRUD, категории, фильтры, статистику, сохранение в JSON.
"""
import argparse
import sys
import json
import os
from datetime import datetime, date
from typing import List, Dict, Optional, Tuple
from dataclasses import dataclass, asdict
from collections import defaultdict

try:
    import tkinter as tk
    from tkinter import ttk, filedialog, messagebox, scrolledtext
    GUI_AVAILABLE = True
except ImportError:
    GUI_AVAILABLE = False

DATA_FILE = "expenses.json"

@dataclass
class Expense:
    id: int
    category: str
    amount: float
    date: str          # YYYY-MM-DD
    description: str

class ExpenseTracker:
    def __init__(self, data_file=DATA_FILE):
        self.data_file = data_file
        self.expenses: List[Expense] = []
        self.next_id = 1
        self.categories = set()
        self.load()

    def load(self):
        if os.path.exists(self.data_file):
            try:
                with open(self.data_file, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                    self.expenses = [Expense(**e) for e in data]
                    self.next_id = max([e.id for e in self.expenses], default=0) + 1
                    self.categories = {e.category for e in self.expenses}
            except:
                self.expenses = []
                self.next_id = 1
                self.categories = set()
        else:
            self.expenses = []
            self.next_id = 1
            self.categories = set()

    def save(self):
        with open(self.data_file, 'w', encoding='utf-8') as f:
            json.dump([asdict(e) for e in self.expenses], f, indent=2, ensure_ascii=False)

    def add_expense(self, category: str, amount: float, date_str: str, description: str = "") -> Expense:
        exp = Expense(self.next_id, category, amount, date_str, description)
        self.expenses.append(exp)
        self.next_id += 1
        self.categories.add(category)
        self.save()
        return exp

    def edit_expense(self, exp_id: int, **kwargs) -> bool:
        for i, e in enumerate(self.expenses):
            if e.id == exp_id:
                for key, val in kwargs.items():
                    if hasattr(e, key) and val is not None:
                        setattr(e, key, val)
                if 'category' in kwargs and kwargs['category']:
                    self.categories.add(kwargs['category'])
                self.save()
                return True
        return False

    def delete_expense(self, exp_id: int) -> bool:
        for i, e in enumerate(self.expenses):
            if e.id == exp_id:
                del self.expenses[i]
                self.save()
                return True
        return False

    def get_expenses(self, category: str = None, date_from: str = None, date_to: str = None,
                     min_amount: float = None, max_amount: float = None) -> List[Expense]:
        result = self.expenses
        if category:
            result = [e for e in result if e.category == category]
        if date_from:
            result = [e for e in result if e.date >= date_from]
        if date_to:
            result = [e for e in result if e.date <= date_to]
        if min_amount is not None:
            result = [e for e in result if e.amount >= min_amount]
        if max_amount is not None:
            result = [e for e in result if e.amount <= max_amount]
        return sorted(result, key=lambda x: x.date)

    def get_statistics(self, category: str = None, date_from: str = None, date_to: str = None) -> Dict:
        expenses = self.get_expenses(category, date_from, date_to)
        total = sum(e.amount for e in expenses)
        by_category = defaultdict(float)
        for e in expenses:
            by_category[e.category] += e.amount
        return {
            'total': total,
            'count': len(expenses),
            'by_category': dict(by_category),
            'average': total / len(expenses) if expenses else 0
        }

    def list_categories(self) -> List[str]:
        return sorted(self.categories)

# ========== CLI ==========
def cli():
    parser = argparse.ArgumentParser(description="Трекер расходов")
    subparsers = parser.add_subparsers(dest="command", help="Команды")

    # add
    add_parser = subparsers.add_parser("add", help="Добавить расход")
    add_parser.add_argument("--category", required=True, help="Категория")
    add_parser.add_argument("--amount", type=float, required=True, help="Сумма")
    add_parser.add_argument("--date", default=date.today().isoformat(), help="Дата (ГГГГ-ММ-ДД)")
    add_parser.add_argument("--description", default="", help="Описание")

    # list
    list_parser = subparsers.add_parser("list", help="Показать расходы")
    list_parser.add_argument("--category", help="Фильтр по категории")
    list_parser.add_argument("--from", dest="date_from", help="Дата от (ГГГГ-ММ-ДД)")
    list_parser.add_argument("--to", dest="date_to", help="Дата до (ГГГГ-ММ-ДД)")
    list_parser.add_argument("--min-amount", type=float, help="Мин. сумма")
    list_parser.add_argument("--max-amount", type=float, help="Макс. сумма")

    # stats
    stats_parser = subparsers.add_parser("stats", help="Статистика")
    stats_parser.add_argument("--category", help="Фильтр по категории")
    stats_parser.add_argument("--from", dest="date_from", help="Дата от")
    stats_parser.add_argument("--to", dest="date_to", help="Дата до")

    # delete
    del_parser = subparsers.add_parser("delete", help="Удалить расход")
    del_parser.add_argument("--id", type=int, required=True, help="ID записи")

    # edit
    edit_parser = subparsers.add_parser("edit", help="Редактировать расход")
    edit_parser.add_argument("--id", type=int, required=True, help="ID записи")
    edit_parser.add_argument("--category", help="Новая категория")
    edit_parser.add_argument("--amount", type=float, help="Новая сумма")
    edit_parser.add_argument("--date", help="Новая дата")
    edit_parser.add_argument("--description", help="Новое описание")

    # interactive mode (без команд)
    parser.add_argument("--gui", action="store_true", help="Запустить GUI")
    args = parser.parse_args()

    if args.gui and GUI_AVAILABLE:
        root = tk.Tk()
        app = ExpenseTrackerGUI(root)
        root.mainloop()
        return

    tracker = ExpenseTracker()
    if args.command == "add":
        exp = tracker.add_expense(args.category, args.amount, args.date, args.description)
        print(f"✅ Добавлен расход #{exp.id}: {exp.amount} {exp.category} ({exp.date})")
    elif args.command == "list":
        expenses = tracker.get_expenses(args.category, args.date_from, args.date_to, args.min_amount, args.max_amount)
        if not expenses:
            print("Нет записей.")
        else:
            total = sum(e.amount for e in expenses)
            print(f"{'ID':<4} {'Дата':<12} {'Категория':<15} {'Сумма':<10} {'Описание'}")
            for e in expenses:
                print(f"{e.id:<4} {e.date:<12} {e.category:<15} {e.amount:<10.2f} {e.description}")
            print(f"\nИтого: {total:.2f}")
    elif args.command == "stats":
        stats = tracker.get_statistics(args.category, args.date_from, args.date_to)
        print(f"📊 Статистика {'(фильтр)' if args.category or args.date_from or args.date_to else ''}")
        print(f"Всего записей: {stats['count']}")
        print(f"Общая сумма: {stats['total']:.2f}")
        print(f"Средний расход: {stats['average']:.2f}")
        if stats['by_category']:
            print("По категориям:")
            for cat, amount in sorted(stats['by_category'].items(), key=lambda x: x[1], reverse=True):
                percent = (amount / stats['total'] * 100) if stats['total'] else 0
                print(f"  {cat}: {amount:.2f} ({percent:.1f}%)")
    elif args.command == "delete":
        if tracker.delete_expense(args.id):
            print(f"✅ Расход #{args.id} удалён")
        else:
            print(f"❌ Расход #{args.id} не найден")
    elif args.command == "edit":
        kwargs = {k: v for k, v in vars(args).items() if k in ['category','amount','date','description'] and v is not None}
        if tracker.edit_expense(args.id, **kwargs):
            print(f"✅ Расход #{args.id} обновлён")
        else:
            print(f"❌ Расход #{args.id} не найден")
    else:
        # интерактивный режим
        interactive_mode(tracker)

def interactive_mode(tracker):
    while True:
        print("\n💰 Трекер расходов (интерактивный)")
        print("1. Добавить расход")
        print("2. Показать расходы (с фильтром)")
        print("3. Статистика")
        print("4. Редактировать")
        print("5. Удалить")
        print("6. Список категорий")
        print("0. Выход")
        choice = input("Выберите действие: ").strip()
        if choice == "0":
            break
        elif choice == "1":
            cat = input("Категория: ").strip()
            if not cat:
                print("Категория обязательна")
                continue
            try:
                amount = float(input("Сумма: ").strip())
            except:
                print("Неверная сумма")
                continue
            date_str = input("Дата (ГГГГ-ММ-ДД, Enter сегодня): ").strip()
            if not date_str:
                date_str = date.today().isoformat()
            desc = input("Описание (необязательно): ").strip()
            exp = tracker.add_expense(cat, amount, date_str, desc)
            print(f"✅ Добавлен расход #{exp.id}")
        elif choice == "2":
            cat = input("Категория (Enter пропустить): ").strip() or None
            date_from = input("Дата от (ГГГГ-ММ-ДД, Enter пропустить): ").strip() or None
            date_to = input("Дата до (ГГГГ-ММ-ДД, Enter пропустить): ").strip() or None
            expenses = tracker.get_expenses(cat, date_from, date_to)
            if not expenses:
                print("Нет записей.")
            else:
                total = sum(e.amount for e in expenses)
                print(f"{'ID':<4} {'Дата':<12} {'Категория':<15} {'Сумма':<10} {'Описание'}")
                for e in expenses:
                    print(f"{e.id:<4} {e.date:<12} {e.category:<15} {e.amount:<10.2f} {e.description}")
                print(f"\nИтого: {total:.2f}")
        elif choice == "3":
            cat = input("Категория (Enter пропустить): ").strip() or None
            date_from = input("Дата от (Enter пропустить): ").strip() or None
            date_to = input("Дата до (Enter пропустить): ").strip() or None
            stats = tracker.get_statistics(cat, date_from, date_to)
            print(f"📊 Статистика")
            print(f"Всего записей: {stats['count']}")
            print(f"Общая сумма: {stats['total']:.2f}")
            print(f"Средний расход: {stats['average']:.2f}")
            if stats['by_category']:
                print("По категориям:")
                for cat, amount in sorted(stats['by_category'].items(), key=lambda x: x[1], reverse=True):
                    percent = (amount / stats['total'] * 100) if stats['total'] else 0
                    print(f"  {cat}: {amount:.2f} ({percent:.1f}%)")
        elif choice == "4":
            try:
                eid = int(input("ID расхода: ").strip())
            except:
                print("Неверный ID")
                continue
            exp = next((e for e in tracker.expenses if e.id == eid), None)
            if not exp:
                print("Запись не найдена")
                continue
            print("Оставьте пустым, чтобы не менять.")
            new_cat = input(f"Категория ({exp.category}): ").strip()
            new_amount = input(f"Сумма ({exp.amount}): ").strip()
            new_date = input(f"Дата ({exp.date}): ").strip()
            new_desc = input(f"Описание ({exp.description}): ").strip()
            kwargs = {}
            if new_cat:
                kwargs['category'] = new_cat
            if new_amount:
                try:
                    kwargs['amount'] = float(new_amount)
                except:
                    pass
            if new_date:
                kwargs['date'] = new_date
            if new_desc:
                kwargs['description'] = new_desc
            if kwargs and tracker.edit_expense(eid, **kwargs):
                print("✅ Обновлено")
            else:
                print("❌ Ошибка обновления")
        elif choice == "5":
            try:
                eid = int(input("ID для удаления: ").strip())
            except:
                print("Неверный ID")
                continue
            if tracker.delete_expense(eid):
                print("✅ Удалено")
            else:
                print("❌ Не найдено")
        elif choice == "6":
            cats = tracker.list_categories()
            if cats:
                print("Категории:", ", ".join(cats))
            else:
                print("Нет категорий")
        else:
            print("Неверный выбор")

# ========== GUI ==========
if GUI_AVAILABLE:
    class ExpenseTrackerGUI:
        def __init__(self, root):
            self.root = root
            self.root.title("💰 Трекер расходов")
            self.root.geometry("800x600")
            self.root.resizable(True, True)
            self.tracker = ExpenseTracker()
            self.create_widgets()
            self.refresh_list()

        def create_widgets(self):
            main = ttk.Frame(self.root, padding="10")
            main.pack(fill=tk.BOTH, expand=True)

            # Верхняя панель: добавление
            add_frame = ttk.LabelFrame(main, text="Добавить расход")
            add_frame.pack(fill=tk.X, pady=5)
            grid = ttk.Frame(add_frame)
            grid.pack(pady=5, padx=5)
            ttk.Label(grid, text="Категория:").grid(row=0, column=0, sticky="w")
            self.cat_entry = ttk.Entry(grid, width=15)
            self.cat_entry.grid(row=0, column=1, padx=5)
            ttk.Label(grid, text="Сумма:").grid(row=0, column=2, sticky="w")
            self.amount_entry = ttk.Entry(grid, width=10)
            self.amount_entry.grid(row=0, column=3, padx=5)
            ttk.Label(grid, text="Дата:").grid(row=0, column=4, sticky="w")
            self.date_entry = ttk.Entry(grid, width=12)
            self.date_entry.insert(0, date.today().isoformat())
            self.date_entry.grid(row=0, column=5, padx=5)
            ttk.Label(grid, text="Описание:").grid(row=0, column=6, sticky="w")
            self.desc_entry = ttk.Entry(grid, width=20)
            self.desc_entry.grid(row=0, column=7, padx=5)
            ttk.Button(grid, text="➕ Добавить", command=self.add_expense).grid(row=0, column=8, padx=5)

            # Фильтры
            filter_frame = ttk.LabelFrame(main, text="Фильтры")
            filter_frame.pack(fill=tk.X, pady=5)
            fgrid = ttk.Frame(filter_frame)
            fgrid.pack(pady=5, padx=5)
            ttk.Label(fgrid, text="Категория:").grid(row=0, column=0, sticky="w")
            self.filter_cat = ttk.Combobox(fgrid, state="readonly")
            self.filter_cat.grid(row=0, column=1, padx=5)
            ttk.Label(fgrid, text="Дата от:").grid(row=0, column=2, sticky="w")
            self.filter_from = ttk.Entry(fgrid, width=12)
            self.filter_from.grid(row=0, column=3, padx=5)
            ttk.Label(fgrid, text="до:").grid(row=0, column=4, sticky="w")
            self.filter_to = ttk.Entry(fgrid, width=12)
            self.filter_to.grid(row=0, column=5, padx=5)
            ttk.Button(fgrid, text="Применить", command=self.apply_filter).grid(row=0, column=6, padx=5)
            ttk.Button(fgrid, text="Сбросить", command=self.reset_filter).grid(row=0, column=7, padx=5)

            # Таблица расходов
            self.tree = ttk.Treeview(main, columns=("id", "date", "category", "amount", "desc"), show="headings")
            self.tree.heading("id", text="ID")
            self.tree.heading("date", text="Дата")
            self.tree.heading("category", text="Категория")
            self.tree.heading("amount", text="Сумма")
            self.tree.heading("desc", text="Описание")
            self.tree.column("id", width=50)
            self.tree.column("date", width=100)
            self.tree.column("category", width=120)
            self.tree.column("amount", width=100)
            self.tree.column("desc", width=300)
            scroll = ttk.Scrollbar(main, orient=tk.VERTICAL, command=self.tree.yview)
            self.tree.configure(yscrollcommand=scroll.set)
            self.tree.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
            scroll.pack(side=tk.RIGHT, fill=tk.Y)

            # Нижняя панель: кнопки
            bottom = ttk.Frame(main)
            bottom.pack(fill=tk.X, pady=5)
            ttk.Button(bottom, text="✏️ Редактировать", command=self.edit_expense).pack(side=tk.LEFT, padx=5)
            ttk.Button(bottom, text="🗑 Удалить", command=self.delete_expense).pack(side=tk.LEFT, padx=5)
            ttk.Button(bottom, text="📊 Статистика", command=self.show_stats).pack(side=tk.LEFT, padx=5)
            ttk.Button(bottom, text="💾 Экспорт CSV", command=self.export_csv).pack(side=tk.LEFT, padx=5)
            ttk.Label(bottom, text="Итого:").pack(side=tk.LEFT, padx=(20,5))
            self.total_label = ttk.Label(bottom, text="0.00")
            self.total_label.pack(side=tk.LEFT)

            self.update_category_list()

        def update_category_list(self):
            cats = self.tracker.list_categories()
            self.filter_cat['values'] = [''] + cats
            self.filter_cat.set('')

        def add_expense(self):
            cat = self.cat_entry.get().strip()
            if not cat:
                messagebox.showerror("Ошибка", "Категория обязательна")
                return
            try:
                amount = float(self.amount_entry.get().strip())
            except:
                messagebox.showerror("Ошибка", "Неверная сумма")
                return
            date_str = self.date_entry.get().strip()
            if not date_str:
                date_str = date.today().isoformat()
            desc = self.desc_entry.get().strip()
            self.tracker.add_expense(cat, amount, date_str, desc)
            self.cat_entry.delete(0, tk.END)
            self.amount_entry.delete(0, tk.END)
            self.desc_entry.delete(0, tk.END)
            self.update_category_list()
            self.refresh_list()

        def refresh_list(self, expenses=None):
            if expenses is None:
                expenses = self.tracker.get_expenses()
            self.tree.delete(*self.tree.get_children())
            total = 0
            for e in expenses:
                self.tree.insert("", tk.END, values=(e.id, e.date, e.category, f"{e.amount:.2f}", e.description))
                total += e.amount
            self.total_label.config(text=f"{total:.2f}")

        def apply_filter(self):
            cat = self.filter_cat.get().strip() or None
            date_from = self.filter_from.get().strip() or None
            date_to = self.filter_to.get().strip() or None
            filtered = self.tracker.get_expenses(cat, date_from, date_to)
            self.refresh_list(filtered)

        def reset_filter(self):
            self.filter_cat.set('')
            self.filter_from.delete(0, tk.END)
            self.filter_to.delete(0, tk.END)
            self.refresh_list()

        def edit_expense(self):
            selected = self.tree.selection()
            if not selected:
                messagebox.showwarning("Выберите запись")
                return
            item = self.tree.item(selected[0])
            exp_id = item['values'][0]
            exp = next((e for e in self.tracker.expenses if e.id == exp_id), None)
            if not exp:
                return
            dialog = tk.Toplevel(self.root)
            dialog.title("Редактировать расход")
            dialog.geometry("400x250")
            tk.Label(dialog, text="Категория:").grid(row=0, column=0, padx=5, pady=5, sticky="w")
            cat_entry = tk.Entry(dialog)
            cat_entry.insert(0, exp.category)
            cat_entry.grid(row=0, column=1, padx=5, pady=5)
            tk.Label(dialog, text="Сумма:").grid(row=1, column=0, padx=5, pady=5, sticky="w")
            amt_entry = tk.Entry(dialog)
            amt_entry.insert(0, str(exp.amount))
            amt_entry.grid(row=1, column=1, padx=5, pady=5)
            tk.Label(dialog, text="Дата (ГГГГ-ММ-ДД):").grid(row=2, column=0, padx=5, pady=5, sticky="w")
            date_entry = tk.Entry(dialog)
            date_entry.insert(0, exp.date)
            date_entry.grid(row=2, column=1, padx=5, pady=5)
            tk.Label(dialog, text="Описание:").grid(row=3, column=0, padx=5, pady=5, sticky="w")
            desc_entry = tk.Entry(dialog)
            desc_entry.insert(0, exp.description)
            desc_entry.grid(row=3, column=1, padx=5, pady=5)

            def save():
                kwargs = {}
                new_cat = cat_entry.get().strip()
                if new_cat:
                    kwargs['category'] = new_cat
                try:
                    new_amt = float(amt_entry.get().strip())
                    kwargs['amount'] = new_amt
                except:
                    pass
                new_date = date_entry.get().strip()
                if new_date:
                    kwargs['date'] = new_date
                new_desc = desc_entry.get().strip()
                if new_desc:
                    kwargs['description'] = new_desc
                if kwargs and self.tracker.edit_expense(exp_id, **kwargs):
                    self.update_category_list()
                    self.refresh_list()
                    dialog.destroy()
                else:
                    messagebox.showerror("Ошибка", "Не удалось обновить")
            tk.Button(dialog, text="Сохранить", command=save).grid(row=4, column=0, columnspan=2, pady=10)

        def delete_expense(self):
            selected = self.tree.selection()
            if not selected:
                messagebox.showwarning("Выберите запись")
                return
            item = self.tree.item(selected[0])
            exp_id = item['values'][0]
            if messagebox.askyesno("Удаление", f"Удалить расход #{exp_id}?"):
                if self.tracker.delete_expense(exp_id):
                    self.update_category_list()
                    self.refresh_list()

        def show_stats(self):
            stats = self.tracker.get_statistics()
            msg = f"📊 Статистика\n\n"
            msg += f"Всего записей: {stats['count']}\n"
            msg += f"Общая сумма: {stats['total']:.2f}\n"
            msg += f"Средний расход: {stats['average']:.2f}\n"
            if stats['by_category']:
                msg += "\nПо категориям:\n"
                for cat, amount in sorted(stats['by_category'].items(), key=lambda x: x[1], reverse=True):
                    percent = (amount / stats['total'] * 100) if stats['total'] else 0
                    msg += f"  {cat}: {amount:.2f} ({percent:.1f}%)\n"
            messagebox.showinfo("Статистика", msg)

        def export_csv(self):
            filepath = filedialog.asksaveasfilename(defaultextension=".csv", filetypes=[("CSV files", "*.csv")])
            if filepath:
                import csv
                with open(filepath, 'w', newline='', encoding='utf-8') as f:
                    writer = csv.writer(f)
                    writer.writerow(["ID", "Дата", "Категория", "Сумма", "Описание"])
                    for e in self.tracker.expenses:
                        writer.writerow([e.id, e.date, e.category, e.amount, e.description])
                messagebox.showinfo("Экспорт", f"Сохранено в {filepath}")

if __name__ == "__main__":
    cli()
