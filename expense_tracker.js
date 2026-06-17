#!/usr/bin/env node
/**
 * expense_tracker.js - Трекер расходов на JavaScript (Node.js CLI)
 */
const fs = require('fs');
const path = require('path');
const { program } = require('commander');
const { v4: uuidv4 } = require('uuid');

const DATA_FILE = path.join(__dirname, 'expenses.json');

class Expense {
    constructor(category, amount, date, description = '') {
        this.id = uuidv4();
        this.category = category;
        this.amount = amount;
        this.date = date || new Date().toISOString().slice(0, 10);
        this.description = description;
    }
}

class Tracker {
    constructor() {
        this.expenses = [];
        this.categories = new Set();
        this.load();
    }

    load() {
        if (fs.existsSync(DATA_FILE)) {
            try {
                const data = JSON.parse(fs.readFileSync(DATA_FILE, 'utf8'));
                this.expenses = data;
                this.categories = new Set(data.map(e => e.category));
            } catch {
                this.expenses = [];
                this.categories = new Set();
            }
        }
    }

    save() {
        fs.writeFileSync(DATA_FILE, JSON.stringify(this.expenses, null, 2), 'utf8');
    }

    add(category, amount, date, description) {
        const exp = new Expense(category, amount, date, description);
        this.expenses.push(exp);
        this.categories.add(category);
        this.save();
        return exp;
    }

    edit(id, updates) {
        const idx = this.expenses.findIndex(e => e.id === id);
        if (idx === -1) return false;
        const exp = this.expenses[idx];
        if (updates.category) {
            exp.category = updates.category;
            this.categories.add(updates.category);
        }
        if (updates.amount !== undefined) exp.amount = updates.amount;
        if (updates.date) exp.date = updates.date;
        if (updates.description !== undefined) exp.description = updates.description;
        this.save();
        return true;
    }

    delete(id) {
        const idx = this.expenses.findIndex(e => e.id === id);
        if (idx === -1) return false;
        this.expenses.splice(idx, 1);
        this.save();
        return true;
    }

    getExpenses(filter = {}) {
        let result = this.expenses;
        if (filter.category) {
            result = result.filter(e => e.category === filter.category);
        }
        if (filter.dateFrom) {
            result = result.filter(e => e.date >= filter.dateFrom);
        }
        if (filter.dateTo) {
            result = result.filter(e => e.date <= filter.dateTo);
        }
        if (filter.minAmount !== undefined) {
            result = result.filter(e => e.amount >= filter.minAmount);
        }
        if (filter.maxAmount !== undefined) {
            result = result.filter(e => e.amount <= filter.maxAmount);
        }
        return result.sort((a, b) => a.date.localeCompare(b.date));
    }

    getStatistics(filter = {}) {
        const expenses = this.getExpenses(filter);
        const total = expenses.reduce((sum, e) => sum + e.amount, 0);
        const byCategory = {};
        expenses.forEach(e => {
            byCategory[e.category] = (byCategory[e.category] || 0) + e.amount;
        });
        return {
            total,
            count: expenses.length,
            byCategory,
            average: expenses.length ? total / expenses.length : 0
        };
    }

    listCategories() {
        return Array.from(this.categories).sort();
    }
}

program
    .command('add')
    .requiredOption('-c, --category <category>', 'Категория')
    .requiredOption('-a, --amount <amount>', 'Сумма', parseFloat)
    .option('-d, --date <date>', 'Дата (ГГГГ-ММ-ДД)')
    .option('-D, --description <description>', 'Описание')
    .action((options) => {
        const tracker = new Tracker();
        const exp = tracker.add(options.category, options.amount, options.date, options.description);
        console.log(`✅ Добавлен расход ${exp.id}: ${exp.amount} ${exp.category} (${exp.date})`);
    });

program
    .command('list')
    .option('-c, --category <category>', 'Категория')
    .option('--from <dateFrom>', 'Дата от')
    .option('--to <dateTo>', 'Дата до')
    .option('--min <minAmount>', 'Мин. сумма', parseFloat)
    .option('--max <maxAmount>', 'Макс. сумма', parseFloat)
    .action((options) => {
        const tracker = new Tracker();
        const expenses = tracker.getExpenses(options);
        if (!expenses.length) {
            console.log('Нет записей.');
            return;
        }
        const total = expenses.reduce((s, e) => s + e.amount, 0);
        console.log('ID      Дата       Категория       Сумма      Описание');
        expenses.forEach(e => {
            console.log(`${e.id.slice(0,8)}  ${e.date}  ${e.category.padEnd(12)}  ${e.amount.toFixed(2)}  ${e.description}`);
        });
        console.log(`\nИтого: ${total.toFixed(2)}`);
    });

program
    .command('stats')
    .option('-c, --category <category>', 'Категория')
    .option('--from <dateFrom>', 'Дата от')
    .option('--to <dateTo>', 'Дата до')
    .action((options) => {
        const tracker = new Tracker();
        const stats = tracker.getStatistics(options);
        console.log(`📊 Статистика`);
        console.log(`Всего записей: ${stats.count}`);
        console.log(`Общая сумма: ${stats.total.toFixed(2)}`);
        console.log(`Средний расход: ${stats.average.toFixed(2)}`);
        if (Object.keys(stats.byCategory).length) {
            console.log('По категориям:');
            const sorted = Object.entries(stats.byCategory).sort((a, b) => b[1] - a[1]);
            sorted.forEach(([cat, amount]) => {
                const percent = stats.total ? (amount / stats.total * 100) : 0;
                console.log(`  ${cat}: ${amount.toFixed(2)} (${percent.toFixed(1)}%)`);
            });
        }
    });

program
    .command('delete')
    .requiredOption('--id <id>', 'ID записи')
    .action((options) => {
        const tracker = new Tracker();
        if (tracker.delete(options.id)) {
            console.log(`✅ Расход ${options.id} удалён`);
        } else {
            console.log(`❌ Расход ${options.id} не найден`);
        }
    });

program
    .command('edit')
    .requiredOption('--id <id>', 'ID записи')
    .option('-c, --category <category>', 'Новая категория')
    .option('-a, --amount <amount>', 'Новая сумма', parseFloat)
    .option('-d, --date <date>', 'Новая дата')
    .option('-D, --description <description>', 'Новое описание')
    .action((options) => {
        const tracker = new Tracker();
        const updates = {};
        if (options.category) updates.category = options.category;
        if (options.amount !== undefined) updates.amount = options.amount;
        if (options.date) updates.date = options.date;
        if (options.description !== undefined) updates.description = options.description;
        if (tracker.edit(options.id, updates)) {
            console.log(`✅ Расход ${options.id} обновлён`);
        } else {
            console.log(`❌ Расход ${options.id} не найден`);
        }
    });

program
    .command('categories')
    .action(() => {
        const tracker = new Tracker();
        const cats = tracker.listCategories();
        if (cats.length) {
            console.log('Категории:', cats.join(', '));
        } else {
            console.log('Нет категорий');
        }
    });

program
    .command('interactive')
    .description('Интерактивный режим')
    .action(() => {
        const tracker = new Tracker();
        const readline = require('readline');
        const rl = readline.createInterface({
            input: process.stdin,
            output: process.stdout
        });

        const prompt = (query) => new Promise((resolve) => rl.question(query, resolve));

        async function interactive() {
            console.log('\n💰 Трекер расходов (интерактивный)');
            let running = true;
            while (running) {
                console.log('\n1. Добавить расход');
                console.log('2. Показать расходы (с фильтром)');
                console.log('3. Статистика');
                console.log('4. Редактировать');
                console.log('5. Удалить');
                console.log('6. Список категорий');
                console.log('0. Выход');
                const choice = await prompt('Выберите действие: ');
                switch (choice.trim()) {
                    case '0': running = false; break;
                    case '1': {
                        const cat = await prompt('Категория: ');
                        if (!cat) { console.log('Категория обязательна'); break; }
                        const amountStr = await prompt('Сумма: ');
                        const amount = parseFloat(amountStr);
                        if (isNaN(amount)) { console.log('Неверная сумма'); break; }
                        let date = await prompt('Дата (ГГГГ-ММ-ДД, Enter сегодня): ');
                        if (!date) date = new Date().toISOString().slice(0, 10);
                        const desc = await prompt('Описание (необязательно): ');
                        const exp = tracker.add(cat, amount, date, desc);
                        console.log(`✅ Добавлен расход ${exp.id}`);
                        break;
                    }
                    case '2': {
                        const cat = await prompt('Категория (Enter пропустить): ') || undefined;
                        const dateFrom = await prompt('Дата от (Enter пропустить): ') || undefined;
                        const dateTo = await prompt('Дата до (Enter пропустить): ') || undefined;
                        const expenses = tracker.getExpenses({ category: cat, dateFrom, dateTo });
                        if (!expenses.length) {
                            console.log('Нет записей.');
                        } else {
                            const total = expenses.reduce((s, e) => s + e.amount, 0);
                            console.log('ID      Дата       Категория       Сумма      Описание');
                            expenses.forEach(e => {
                                console.log(`${e.id.slice(0,8)}  ${e.date}  ${e.category.padEnd(12)}  ${e.amount.toFixed(2)}  ${e.description}`);
                            });
                            console.log(`\nИтого: ${total.toFixed(2)}`);
                        }
                        break;
                    }
                    case '3': {
                        const cat = await prompt('Категория (Enter пропустить): ') || undefined;
                        const dateFrom = await prompt('Дата от (Enter пропустить): ') || undefined;
                        const dateTo = await prompt('Дата до (Enter пропустить): ') || undefined;
                        const stats = tracker.getStatistics({ category: cat, dateFrom, dateTo });
                        console.log(`📊 Статистика`);
                        console.log(`Всего записей: ${stats.count}`);
                        console.log(`Общая сумма: ${stats.total.toFixed(2)}`);
                        console.log(`Средний расход: ${stats.average.toFixed(2)}`);
                        if (Object.keys(stats.byCategory).length) {
                            console.log('По категориям:');
                            const sorted = Object.entries(stats.byCategory).sort((a, b) => b[1] - a[1]);
                            sorted.forEach(([cat, amount]) => {
                                const percent = stats.total ? (amount / stats.total * 100) : 0;
                                console.log(`  ${cat}: ${amount.toFixed(2)} (${percent.toFixed(1)}%)`);
                            });
                        }
                        break;
                    }
                    case '4': {
                        const id = await prompt('ID расхода: ');
                        if (!id) break;
                        const exp = tracker.expenses.find(e => e.id === id);
                        if (!exp) { console.log('Запись не найдена'); break; }
                        console.log('Оставьте пустым, чтобы не менять.');
                        const newCat = await prompt(`Категория (${exp.category}): `);
                        const newAmount = await prompt(`Сумма (${exp.amount}): `);
                        const newDate = await prompt(`Дата (${exp.date}): `);
                        const newDesc = await prompt(`Описание (${exp.description}): `);
                        const updates = {};
                        if (newCat) updates.category = newCat;
                        if (newAmount) {
                            const a = parseFloat(newAmount);
                            if (!isNaN(a)) updates.amount = a;
                        }
                        if (newDate) updates.date = newDate;
                        if (newDesc) updates.description = newDesc;
                        if (Object.keys(updates).length && tracker.edit(id, updates)) {
                            console.log('✅ Обновлено');
                        } else {
                            console.log('❌ Ошибка обновления');
                        }
                        break;
                    }
                    case '5': {
                        const id = await prompt('ID для удаления: ');
                        if (!id) break;
                        if (tracker.delete(id)) {
                            console.log('✅ Удалено');
                        } else {
                            console.log('❌ Не найдено');
                        }
                        break;
                    }
                    case '6': {
                        const cats = tracker.listCategories();
                        if (cats.length) {
                            console.log('Категории:', cats.join(', '));
                        } else {
                            console.log('Нет категорий');
                        }
                        break;
                    }
                    default: console.log('Неверный выбор');
                }
            }
            rl.close();
        }
        interactive();
    });

if (process.argv.length <= 2) {
    process.argv.push('interactive');
}
program.parse(process.argv);
