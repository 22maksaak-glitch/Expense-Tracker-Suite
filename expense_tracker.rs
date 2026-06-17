// expense_tracker.rs - Трекер расходов на Rust (CLI)
use serde::{Serialize, Deserialize};
use std::collections::HashMap;
use std::fs;
use std::io::{self, Write, BufRead};
use std::path::Path;
use std::str::FromStr;
use chrono::NaiveDate;

#[derive(Serialize, Deserialize, Clone)]
struct Expense {
    id: u32,
    category: String,
    amount: f64,
    date: String,
    description: String,
}

impl Expense {
    fn new(id: u32, category: &str, amount: f64, date: &str, description: &str) -> Self {
        Expense {
            id,
            category: category.to_string(),
            amount,
            date: date.to_string(),
            description: description.to_string(),
        }
    }
}

struct Tracker {
    expenses: Vec<Expense>,
    next_id: u32,
    categories: std::collections::HashSet<String>,
}

impl Tracker {
    fn new() -> Self {
        let mut t = Tracker {
            expenses: Vec::new(),
            next_id: 1,
            categories: std::collections::HashSet::new(),
        };
        t.load();
        t
    }

    fn load(&mut self) {
        let path = "expenses.json";
        if Path::new(path).exists() {
            if let Ok(data) = fs::read_to_string(path) {
                if let Ok(parsed) = serde_json::from_str::<Vec<Expense>>(&data) {
                    self.expenses = parsed;
                    self.next_id = self.expenses.iter().map(|e| e.id).max().unwrap_or(0) + 1;
                    self.categories = self.expenses.iter().map(|e| e.category.clone()).collect();
                    return;
                }
            }
        }
        self.expenses = Vec::new();
        self.next_id = 1;
        self.categories = std::collections::HashSet::new();
    }

    fn save(&self) {
        let data = serde_json::to_string_pretty(&self.expenses).unwrap();
        fs::write("expenses.json", data).unwrap();
    }

    fn add(&mut self, category: &str, amount: f64, date: &str, description: &str) -> Expense {
        let exp = Expense::new(self.next_id, category, amount, date, description);
        self.expenses.push(exp.clone());
        self.next_id += 1;
        self.categories.insert(category.to_string());
        self.save();
        exp
    }

    fn edit(&mut self, id: u32, category: Option<&str>, amount: Option<f64>, date: Option<&str>, description: Option<&str>) -> bool {
        for exp in &mut self.expenses {
            if exp.id == id {
                if let Some(c) = category {
                    exp.category = c.to_string();
                    self.categories.insert(c.to_string());
                }
                if let Some(a) = amount {
                    exp.amount = a;
                }
                if let Some(d) = date {
                    exp.date = d.to_string();
                }
                if let Some(d) = description {
                    exp.description = d.to_string();
                }
                self.save();
                return true;
            }
        }
        false
    }

    fn delete(&mut self, id: u32) -> bool {
        let len = self.expenses.len();
        self.expenses.retain(|e| e.id != id);
        if self.expenses.len() < len {
            self.save();
            return true;
        }
        false
    }

    fn get_expenses(&self, category: Option<&str>, date_from: Option<&str>, date_to: Option<&str>,
                   min_amount: Option<f64>, max_amount: Option<f64>) -> Vec<Expense> {
        let mut result = self.expenses.clone();
        if let Some(cat) = category {
            result.retain(|e| e.category == cat);
        }
        if let Some(from) = date_from {
            result.retain(|e| e.date >= from);
        }
        if let Some(to) = date_to {
            result.retain(|e| e.date <= to);
        }
        if let Some(min) = min_amount {
            result.retain(|e| e.amount >= min);
        }
        if let Some(max) = max_amount {
            result.retain(|e| e.amount <= max);
        }
        result.sort_by(|a, b| a.date.cmp(&b.date));
        result
    }

    fn get_statistics(&self, category: Option<&str>, date_from: Option<&str>, date_to: Option<&str>) -> (f64, usize, HashMap<String, f64>, f64) {
        let expenses = self.get_expenses(category, date_from, date_to, None, None);
        let total = expenses.iter().map(|e| e.amount).sum();
        let count = expenses.len();
        let mut by_category = HashMap::new();
        for e in &expenses {
            *by_category.entry(e.category.clone()).or_insert(0.0) += e.amount;
        }
        let avg = if count > 0 { total / count as f64 } else { 0.0 };
        (total, count, by_category, avg)
    }

    fn list_categories(&self) -> Vec<String> {
        let mut v: Vec<_> = self.categories.iter().cloned().collect();
        v.sort();
        v
    }
}

fn main() {
    let args: Vec<String> = std::env::args().collect();
    if args.len() < 2 {
        interactive_mode();
        return;
    }
    let mut tracker = Tracker::new();
    match args[1].as_str() {
        "add" => {
            let mut category = String::new();
            let mut amount = 0.0;
            let mut date = String::new();
            let mut description = String::new();
            let mut i = 2;
            while i < args.len() {
                match args[i].as_str() {
                    "--category" => { category = args[i+1].clone(); i += 2; }
                    "--amount" => { amount = args[i+1].parse().unwrap_or(0.0); i += 2; }
                    "--date" => { date = args[i+1].clone(); i += 2; }
                    "--description" => { description = args[i+1].clone(); i += 2; }
                    _ => { i += 1; }
                }
            }
            if category.is_empty() || amount <= 0.0 {
                println!("Укажите --category и --amount");
                return;
            }
            if date.is_empty() {
                date = chrono::Local::now().format("%Y-%m-%d").to_string();
            }
            let exp = tracker.add(&category, amount, &date, &description);
            println!("✅ Добавлен расход #{}: {:.2} {} ({})", exp.id, exp.amount, exp.category, exp.date);
        }
        "list" => {
            let mut category = None;
            let mut date_from = None;
            let mut date_to = None;
            let mut i = 2;
            while i < args.len() {
                match args[i].as_str() {
                    "--category" => { category = Some(args[i+1].clone()); i += 2; }
                    "--from" => { date_from = Some(args[i+1].clone()); i += 2; }
                    "--to" => { date_to = Some(args[i+1].clone()); i += 2; }
                    _ => { i += 1; }
                }
            }
            let expenses = tracker.get_expenses(category.as_deref(), date_from.as_deref(), date_to.as_deref(), None, None);
            if expenses.is_empty() {
                println!("Нет записей.");
            } else {
                let total: f64 = expenses.iter().map(|e| e.amount).sum();
                println!("{:<4} {:<12} {:<15} {:<10} {}", "ID", "Дата", "Категория", "Сумма", "Описание");
                for e in &expenses {
                    println!("{:<4} {:<12} {:<15} {:<10.2} {}", e.id, e.date, e.category, e.amount, e.description);
                }
                println!("\nИтого: {:.2}", total);
            }
        }
        "stats" => {
            let mut category = None;
            let mut date_from = None;
            let mut date_to = None;
            let mut i = 2;
            while i < args.len() {
                match args[i].as_str() {
                    "--category" => { category = Some(args[i+1].clone()); i += 2; }
                    "--from" => { date_from = Some(args[i+1].clone()); i += 2; }
                    "--to" => { date_to = Some(args[i+1].clone()); i += 2; }
                    _ => { i += 1; }
                }
            }
            let (total, count, by_category, avg) = tracker.get_statistics(category.as_deref(), date_from.as_deref(), date_to.as_deref());
            println!("📊 Статистика");
            println!("Всего записей: {}", count);
            println!("Общая сумма: {:.2}", total);
            println!("Средний расход: {:.2}", avg);
            if !by_category.is_empty() {
                println!("По категориям:");
                let mut sorted: Vec<_> = by_category.into_iter().collect();
                sorted.sort_by(|a, b| b.1.partial_cmp(&a.1).unwrap());
                for (cat, amount) in sorted {
                    let percent = if total > 0.0 { (amount / total) * 100.0 } else { 0.0 };
                    println!("  {}: {:.2} ({:.1}%)", cat, amount, percent);
                }
            }
        }
        "delete" => {
            let mut id = 0;
            let mut i = 2;
            while i < args.len() {
                if args[i] == "--id" {
                    id = args[i+1].parse().unwrap_or(0);
                    break;
                }
                i += 1;
            }
            if id == 0 {
                println!("Укажите --id");
                return;
            }
            if tracker.delete(id) {
                println!("✅ Расход #{} удалён", id);
            } else {
                println!("❌ Расход #{} не найден", id);
            }
        }
        "edit" => {
            let mut id = 0;
            let mut category = None;
            let mut amount = None;
            let mut date = None;
            let mut description = None;
            let mut i = 2;
            while i < args.len() {
                match args[i].as_str() {
                    "--id" => { id = args[i+1].parse().unwrap_or(0); i += 2; }
                    "--category" => { category = Some(args[i+1].clone()); i += 2; }
                    "--amount" => { amount = Some(args[i+1].parse().unwrap_or(0.0)); i += 2; }
                    "--date" => { date = Some(args[i+1].clone()); i += 2; }
                    "--description" => { description = Some(args[i+1].clone()); i += 2; }
                    _ => { i += 1; }
                }
            }
            if id == 0 {
                println!("Укажите --id");
                return;
            }
            if tracker.edit(id, category.as_deref(), amount, date.as_deref(), description.as_deref()) {
                println!("✅ Расход #{} обновлён", id);
            } else {
                println!("❌ Расход #{} не найден", id);
            }
        }
        "categories" => {
            let cats = tracker.list_categories();
            if cats.is_empty() {
                println!("Нет категорий");
            } else {
                println!("Категории: {}", cats.join(", "));
            }
        }
        _ => {
            interactive_mode();
        }
    }
}

fn interactive_mode() {
    let mut tracker = Tracker::new();
    let stdin = io::stdin();
    let mut stdout = io::stdout();
    loop {
        println!("\n💰 Трекер расходов (интерактивный)");
        println!("1. Добавить расход");
        println!("2. Показать расходы (с фильтром)");
        println!("3. Статистика");
        println!("4. Редактировать");
        println!("5. Удалить");
        println!("6. Список категорий");
        println!("0. Выход");
        print!("Выберите действие: ");
        stdout.flush().unwrap();
        let mut choice = String::new();
        stdin.read_line(&mut choice).unwrap();
        match choice.trim() {
            "0" => break,
            "1" => {
                print!("Категория: ");
                stdout.flush().unwrap();
                let mut cat = String::new();
                stdin.read_line(&mut cat).unwrap();
                let cat = cat.trim();
                if cat.is_empty() {
                    println!("Категория обязательна");
                    continue;
                }
                print!("Сумма: ");
                stdout.flush().unwrap();
                let mut amt = String::new();
                stdin.read_line(&mut amt).unwrap();
                let amount = match amt.trim().parse::<f64>() {
                    Ok(v) if v > 0.0 => v,
                    _ => { println!("Неверная сумма"); continue; }
                };
                print!("Дата (ГГГГ-ММ-ДД, Enter сегодня): ");
                stdout.flush().unwrap();
                let mut date = String::new();
                stdin.read_line(&mut date).unwrap();
                let date = if date.trim().is_empty() {
                    chrono::Local::now().format("%Y-%m-%d").to_string()
                } else {
                    date.trim().to_string()
                };
                print!("Описание (необязательно): ");
                stdout.flush().unwrap();
                let mut desc = String::new();
                stdin.read_line(&mut desc).unwrap();
                let exp = tracker.add(cat, amount, &date, desc.trim());
                println!("✅ Добавлен расход #{}", exp.id);
            }
            "2" => {
                print!("Категория (Enter пропустить): ");
                stdout.flush().unwrap();
                let mut cat = String::new();
                stdin.read_line(&mut cat).unwrap();
                let cat = if cat.trim().is_empty() { None } else { Some(cat.trim()) };
                print!("Дата от (Enter пропустить): ");
                stdout.flush().unwrap();
                let mut from = String::new();
                stdin.read_line(&mut from).unwrap();
                let from = if from.trim().is_empty() { None } else { Some(from.trim()) };
                print!("Дата до (Enter пропустить): ");
                stdout.flush().unwrap();
                let mut to = String::new();
                stdin.read_line(&mut to).unwrap();
                let to = if to.trim().is_empty() { None } else { Some(to.trim()) };
                let expenses = tracker.get_expenses(cat, from, to, None, None);
                if expenses.is_empty() {
                    println!("Нет записей.");
                } else {
                    let total: f64 = expenses.iter().map(|e| e.amount).sum();
                    println!("{:<4} {:<12} {:<15} {:<10} {}", "ID", "Дата", "Категория", "Сумма", "Описание");
                    for e in &expenses {
                        println!("{:<4} {:<12} {:<15} {:<10.2} {}", e.id, e.date, e.category, e.amount, e.description);
                    }
                    println!("\nИтого: {:.2}", total);
                }
            }
            "3" => {
                print!("Категория (Enter пропустить): ");
                stdout.flush().unwrap();
                let mut cat = String::new();
                stdin.read_line(&mut cat).unwrap();
                let cat = if cat.trim().is_empty() { None } else { Some(cat.trim()) };
                print!("Дата от (Enter пропустить): ");
                stdout.flush().unwrap();
                let mut from = String::new();
                stdin.read_line(&mut from).unwrap();
                let from = if from.trim().is_empty() { None } else { Some(from.trim()) };
                print!("Дата до (Enter пропустить): ");
                stdout.flush().unwrap();
                let mut to = String::new();
                stdin.read_line(&mut to).unwrap();
                let to = if to.trim().is_empty() { None } else { Some(to.trim()) };
                let (total, count, by_category, avg) = tracker.get_statistics(cat, from, to);
                println!("📊 Статистика");
                println!("Всего записей: {}", count);
                println!("Общая сумма: {:.2}", total);
                println!("Средний расход: {:.2}", avg);
                if !by_category.is_empty() {
                    println!("По категориям:");
                    let mut sorted: Vec<_> = by_category.into_iter().collect();
                    sorted.sort_by(|a, b| b.1.partial_cmp(&a.1).unwrap());
                    for (cat, amount) in sorted {
                        let percent = if total > 0.0 { (amount / total) * 100.0 } else { 0.0 };
                        println!("  {}: {:.2} ({:.1}%)", cat, amount, percent);
                    }
                }
            }
            "4" => {
                print!("ID расхода: ");
                stdout.flush().unwrap();
                let mut id_str = String::new();
                stdin.read_line(&mut id_str).unwrap();
                let id = match id_str.trim().parse::<u32>() {
                    Ok(v) => v,
                    _ => { println!("Неверный ID"); continue; }
                };
                let exp = tracker.expenses.iter().find(|e| e.id == id).cloned();
                let exp = match exp {
                    Some(e) => e,
                    None => { println!("Запись не найдена"); continue; }
                };
                println!("Оставьте пустым, чтобы не менять.");
                print!("Категория ({}): ", exp.category);
                stdout.flush().unwrap();
                let mut new_cat = String::new();
                stdin.read_line(&mut new_cat).unwrap();
                let new_cat = if new_cat.trim().is_empty() { None } else { Some(new_cat.trim()) };
                print!("Сумма ({:.2}): ", exp.amount);
                stdout.flush().unwrap();
                let mut new_amt = String::new();
                stdin.read_line(&mut new_amt).unwrap();
                let new_amt = if new_amt.trim().is_empty() { None } else {
                    match new_amt.trim().parse::<f64>() {
                        Ok(v) => Some(v),
                        _ => None
                    }
                };
                print!("Дата ({}): ", exp.date);
                stdout.flush().unwrap();
                let mut new_date = String::new();
                stdin.read_line(&mut new_date).unwrap();
                let new_date = if new_date.trim().is_empty() { None } else { Some(new_date.trim()) };
                print!("Описание ({}): ", exp.description);
                stdout.flush().unwrap();
                let mut new_desc = String::new();
                stdin.read_line(&mut new_desc).unwrap();
                let new_desc = if new_desc.trim().is_empty() { None } else { Some(new_desc.trim()) };
                if tracker.edit(id, new_cat, new_amt, new_date, new_desc) {
                    println!("✅ Обновлено");
                } else {
                    println!("❌ Ошибка обновления");
                }
            }
            "5" => {
                print!("ID для удаления: ");
                stdout.flush().unwrap();
                let mut id_str = String::new();
                stdin.read_line(&mut id_str).unwrap();
                let id = match id_str.trim().parse::<u32>() {
                    Ok(v) => v,
                    _ => { println!("Неверный ID"); continue; }
                };
                if tracker.delete(id) {
                    println!("✅ Удалено");
                } else {
                    println!("❌ Не найдено");
                }
            }
            "6" => {
                let cats = tracker.list_categories();
                if cats.is_empty() {
                    println!("Нет категорий");
                } else {
                    println!("Категории: {}", cats.join(", "));
                }
            }
            _ => println!("Неверный выбор"),
        }
    }
}
