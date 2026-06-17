// expense_tracker.go - Трекер расходов на Go (CLI)
package main

import (
	"bufio"
	"encoding/json"
	"flag"
	"fmt"
	"os"
	"sort"
	"strconv"
	"strings"
	"time"
)

type Expense struct {
	ID          int     `json:"id"`
	Category    string  `json:"category"`
	Amount      float64 `json:"amount"`
	Date        string  `json:"date"`
	Description string  `json:"description"`
}

type Tracker struct {
	Expenses   []Expense       `json:"expenses"`
	NextID     int             `json:"next_id"`
	Categories map[string]bool `json:"-"`
}

const dataFile = "expenses.json"

func (t *Tracker) load() {
	file, err := os.ReadFile(dataFile)
	if err != nil {
		t.Expenses = []Expense{}
		t.NextID = 1
		t.Categories = make(map[string]bool)
		return
	}
	err = json.Unmarshal(file, t)
	if err != nil {
		t.Expenses = []Expense{}
		t.NextID = 1
	}
	t.Categories = make(map[string]bool)
	for _, e := range t.Expenses {
		t.Categories[e.Category] = true
	}
}

func (t *Tracker) save() {
	data, _ := json.MarshalIndent(t, "", "  ")
	os.WriteFile(dataFile, data, 0644)
}

func (t *Tracker) add(category string, amount float64, date string, description string) Expense {
	if date == "" {
		date = time.Now().Format("2006-01-02")
	}
	e := Expense{
		ID:          t.NextID,
		Category:    category,
		Amount:      amount,
		Date:        date,
		Description: description,
	}
	t.Expenses = append(t.Expenses, e)
	t.NextID++
	t.Categories[category] = true
	t.save()
	return e
}

func (t *Tracker) edit(id int, category *string, amount *float64, date *string, description *string) bool {
	for i, e := range t.Expenses {
		if e.ID == id {
			if category != nil {
				t.Expenses[i].Category = *category
				t.Categories[*category] = true
			}
			if amount != nil {
				t.Expenses[i].Amount = *amount
			}
			if date != nil {
				t.Expenses[i].Date = *date
			}
			if description != nil {
				t.Expenses[i].Description = *description
			}
			t.save()
			return true
		}
	}
	return false
}

func (t *Tracker) delete(id int) bool {
	for i, e := range t.Expenses {
		if e.ID == id {
			t.Expenses = append(t.Expenses[:i], t.Expenses[i+1:]...)
			t.save()
			return true
		}
	}
	return false
}

func (t *Tracker) getExpenses(category *string, dateFrom, dateTo *string, minAmount, maxAmount *float64) []Expense {
	result := t.Expenses
	if category != nil {
		result = filterByCategory(result, *category)
	}
	if dateFrom != nil {
		result = filterByDateFrom(result, *dateFrom)
	}
	if dateTo != nil {
		result = filterByDateTo(result, *dateTo)
	}
	if minAmount != nil {
		result = filterByMinAmount(result, *minAmount)
	}
	if maxAmount != nil {
		result = filterByMaxAmount(result, *maxAmount)
	}
	sort.Slice(result, func(i, j int) bool {
		return result[i].Date < result[j].Date
	})
	return result
}

func filterByCategory(expenses []Expense, cat string) []Expense {
	var res []Expense
	for _, e := range expenses {
		if e.Category == cat {
			res = append(res, e)
		}
	}
	return res
}

func filterByDateFrom(expenses []Expense, from string) []Expense {
	var res []Expense
	for _, e := range expenses {
		if e.Date >= from {
			res = append(res, e)
		}
	}
	return res
}

func filterByDateTo(expenses []Expense, to string) []Expense {
	var res []Expense
	for _, e := range expenses {
		if e.Date <= to {
			res = append(res, e)
		}
	}
	return res
}

func filterByMinAmount(expenses []Expense, min float64) []Expense {
	var res []Expense
	for _, e := range expenses {
		if e.Amount >= min {
			res = append(res, e)
		}
	}
	return res
}

func filterByMaxAmount(expenses []Expense, max float64) []Expense {
	var res []Expense
	for _, e := range expenses {
		if e.Amount <= max {
			res = append(res, e)
		}
	}
	return res
}

func (t *Tracker) getStatistics(category *string, dateFrom, dateTo *string) (total float64, count int, byCategory map[string]float64, avg float64) {
	expenses := t.getExpenses(category, dateFrom, dateTo, nil, nil)
	total = 0
	byCategory = make(map[string]float64)
	for _, e := range expenses {
		total += e.Amount
		byCategory[e.Category] += e.Amount
	}
	count = len(expenses)
	if count > 0 {
		avg = total / float64(count)
	}
	return
}

func (t *Tracker) listCategories() []string {
	var cats []string
	for c := range t.Categories {
		cats = append(cats, c)
	}
	sort.Strings(cats)
	return cats
}

func main() {
	var (
		cmd         string
		category    string
		amount      float64
		date        string
		description string
		id          int
		from        string
		to          string
		minAmt      float64
		maxAmt      float64
	)
	flag.StringVar(&cmd, "cmd", "", "Команда: add, list, stats, delete, edit, categories")
	flag.StringVar(&category, "category", "", "Категория")
	flag.Float64Var(&amount, "amount", 0, "Сумма")
	flag.StringVar(&date, "date", "", "Дата")
	flag.StringVar(&description, "description", "", "Описание")
	flag.IntVar(&id, "id", 0, "ID записи")
	flag.StringVar(&from, "from", "", "Дата от")
	flag.StringVar(&to, "to", "", "Дата до")
	flag.Float64Var(&minAmt, "min", 0, "Мин. сумма")
	flag.Float64Var(&maxAmt, "max", 0, "Макс. сумма")
	flag.Parse()

	tracker := &Tracker{}
	tracker.load()

	switch cmd {
	case "add":
		if category == "" || amount <= 0 {
			fmt.Println("Необходимо указать --category и --amount")
			return
		}
		if date == "" {
			date = time.Now().Format("2006-01-02")
		}
		e := tracker.add(category, amount, date, description)
		fmt.Printf("✅ Добавлен расход #%d: %.2f %s (%s)\n", e.ID, e.Amount, e.Category, e.Date)
	case "list":
		expenses := tracker.getExpenses(&category, &from, &to, &minAmt, &maxAmt)
		if len(expenses) == 0 {
			fmt.Println("Нет записей.")
		} else {
			total := 0.0
			fmt.Printf("%-4s %-12s %-15s %-10s %s\n", "ID", "Дата", "Категория", "Сумма", "Описание")
			for _, e := range expenses {
				fmt.Printf("%-4d %-12s %-15s %-10.2f %s\n", e.ID, e.Date, e.Category, e.Amount, e.Description)
				total += e.Amount
			}
			fmt.Printf("\nИтого: %.2f\n", total)
		}
	case "stats":
		var catPtr *string
		if category != "" {
			catPtr = &category
		}
		var fromPtr, toPtr *string
		if from != "" {
			fromPtr = &from
		}
		if to != "" {
			toPtr = &to
		}
		total, count, byCategory, avg := tracker.getStatistics(catPtr, fromPtr, toPtr)
		fmt.Printf("📊 Статистика\n")
		fmt.Printf("Всего записей: %d\n", count)
		fmt.Printf("Общая сумма: %.2f\n", total)
		fmt.Printf("Средний расход: %.2f\n", avg)
		if len(byCategory) > 0 {
			fmt.Println("По категориям:")
			type kv struct {
				Key   string
				Value float64
			}
			var sorted []kv
			for k, v := range byCategory {
				sorted = append(sorted, kv{k, v})
			}
			sort.Slice(sorted, func(i, j int) bool {
				return sorted[i].Value > sorted[j].Value
			})
			for _, kv := range sorted {
				percent := (kv.Value / total) * 100
				if total == 0 {
					percent = 0
				}
				fmt.Printf("  %s: %.2f (%.1f%%)\n", kv.Key, kv.Value, percent)
			}
		}
	case "delete":
		if id == 0 {
			fmt.Println("Укажите --id")
			return
		}
		if tracker.delete(id) {
			fmt.Printf("✅ Расход #%d удалён\n", id)
		} else {
			fmt.Printf("❌ Расход #%d не найден\n", id)
		}
	case "edit":
		if id == 0 {
			fmt.Println("Укажите --id")
			return
		}
		var catPtr *string
		var amtPtr *float64
		var datePtr *string
		var descPtr *string
		if category != "" {
			catPtr = &category
		}
		if amount > 0 {
			amtPtr = &amount
		}
		if date != "" {
			datePtr = &date
		}
		if description != "" {
			descPtr = &description
		}
		if tracker.edit(id, catPtr, amtPtr, datePtr, descPtr) {
			fmt.Printf("✅ Расход #%d обновлён\n", id)
		} else {
			fmt.Printf("❌ Расход #%d не найден\n", id)
		}
	case "categories":
		cats := tracker.listCategories()
		if len(cats) > 0 {
			fmt.Println("Категории:", strings.Join(cats, ", "))
		} else {
			fmt.Println("Нет категорий")
		}
	default:
		// interactive mode
		interactiveMode(tracker)
	}
}

func interactiveMode(tracker *Tracker) {
	scanner := bufio.NewScanner(os.Stdin)
	for {
		fmt.Println("\n💰 Трекер расходов (интерактивный)")
		fmt.Println("1. Добавить расход")
		fmt.Println("2. Показать расходы (с фильтром)")
		fmt.Println("3. Статистика")
		fmt.Println("4. Редактировать")
		fmt.Println("5. Удалить")
		fmt.Println("6. Список категорий")
		fmt.Println("0. Выход")
		fmt.Print("Выберите действие: ")
		scanner.Scan()
		choice := scanner.Text()
		switch choice {
		case "0":
			return
		case "1":
			fmt.Print("Категория: ")
			scanner.Scan()
			cat := scanner.Text()
			if cat == "" {
				fmt.Println("Категория обязательна")
				continue
			}
			fmt.Print("Сумма: ")
			scanner.Scan()
			amtStr := scanner.Text()
			amt, err := strconv.ParseFloat(amtStr, 64)
			if err != nil || amt <= 0 {
				fmt.Println("Неверная сумма")
				continue
			}
			fmt.Print("Дата (ГГГГ-ММ-ДД, Enter сегодня): ")
			scanner.Scan()
			date := scanner.Text()
			if date == "" {
				date = time.Now().Format("2006-01-02")
			}
			fmt.Print("Описание (необязательно): ")
			scanner.Scan()
			desc := scanner.Text()
			e := tracker.add(cat, amt, date, desc)
			fmt.Printf("✅ Добавлен расход #%d\n", e.ID)
		case "2":
			fmt.Print("Категория (Enter пропустить): ")
			scanner.Scan()
			cat := scanner.Text()
			var catPtr *string
			if cat != "" {
				catPtr = &cat
			}
			fmt.Print("Дата от (Enter пропустить): ")
			scanner.Scan()
			from := scanner.Text()
			var fromPtr *string
			if from != "" {
				fromPtr = &from
			}
			fmt.Print("Дата до (Enter пропустить): ")
			scanner.Scan()
			to := scanner.Text()
			var toPtr *string
			if to != "" {
				toPtr = &to
			}
			expenses := tracker.getExpenses(catPtr, fromPtr, toPtr, nil, nil)
			if len(expenses) == 0 {
				fmt.Println("Нет записей.")
			} else {
				total := 0.0
				fmt.Printf("%-4s %-12s %-15s %-10s %s\n", "ID", "Дата", "Категория", "Сумма", "Описание")
				for _, e := range expenses {
					fmt.Printf("%-4d %-12s %-15s %-10.2f %s\n", e.ID, e.Date, e.Category, e.Amount, e.Description)
					total += e.Amount
				}
				fmt.Printf("\nИтого: %.2f\n", total)
			}
		case "3":
			fmt.Print("Категория (Enter пропустить): ")
			scanner.Scan()
			cat := scanner.Text()
			var catPtr *string
			if cat != "" {
				catPtr = &cat
			}
			fmt.Print("Дата от (Enter пропустить): ")
			scanner.Scan()
			from := scanner.Text()
			var fromPtr *string
			if from != "" {
				fromPtr = &from
			}
			fmt.Print("Дата до (Enter пропустить): ")
			scanner.Scan()
			to := scanner.Text()
			var toPtr *string
			if to != "" {
				toPtr = &to
			}
			total, count, byCategory, avg := tracker.getStatistics(catPtr, fromPtr, toPtr)
			fmt.Printf("📊 Статистика\n")
			fmt.Printf("Всего записей: %d\n", count)
			fmt.Printf("Общая сумма: %.2f\n", total)
			fmt.Printf("Средний расход: %.2f\n", avg)
			if len(byCategory) > 0 {
				fmt.Println("По категориям:")
				type kv struct {
					Key   string
					Value float64
				}
				var sorted []kv
				for k, v := range byCategory {
					sorted = append(sorted, kv{k, v})
				}
				sort.Slice(sorted, func(i, j int) bool {
					return sorted[i].Value > sorted[j].Value
				})
				for _, kv := range sorted {
					percent := (kv.Value / total) * 100
					if total == 0 {
						percent = 0
					}
					fmt.Printf("  %s: %.2f (%.1f%%)\n", kv.Key, kv.Value, percent)
				}
			}
		case "4":
			fmt.Print("ID расхода: ")
			scanner.Scan()
			idStr := scanner.Text()
			id, err := strconv.Atoi(idStr)
			if err != nil {
				fmt.Println("Неверный ID")
				continue
			}
			var exp *Expense
			for _, e := range tracker.Expenses {
				if e.ID == id {
					exp = &e
					break
				}
			}
			if exp == nil {
				fmt.Println("Запись не найдена")
				continue
			}
			fmt.Println("Оставьте пустым, чтобы не менять.")
			fmt.Printf("Категория (%s): ", exp.Category)
			scanner.Scan()
			newCat := scanner.Text()
			fmt.Printf("Сумма (%.2f): ", exp.Amount)
			scanner.Scan()
			newAmtStr := scanner.Text()
			var newAmt *float64
			if newAmtStr != "" {
				if a, err := strconv.ParseFloat(newAmtStr, 64); err == nil {
					newAmt = &a
				}
			}
			fmt.Printf("Дата (%s): ", exp.Date)
			scanner.Scan()
			newDate := scanner.Text()
			var newDatePtr *string
			if newDate != "" {
				newDatePtr = &newDate
			}
			fmt.Printf("Описание (%s): ", exp.Description)
			scanner.Scan()
			newDesc := scanner.Text()
			var newDescPtr *string
			if newDesc != "" {
				newDescPtr = &newDesc
			}
			var catPtr *string
			if newCat != "" {
				catPtr = &newCat
			}
			if tracker.edit(id, catPtr, newAmt, newDatePtr, newDescPtr) {
				fmt.Println("✅ Обновлено")
			} else {
				fmt.Println("❌ Ошибка обновления")
			}
		case "5":
			fmt.Print("ID для удаления: ")
			scanner.Scan()
			idStr := scanner.Text()
			id, err := strconv.Atoi(idStr)
			if err != nil {
				fmt.Println("Неверный ID")
				continue
			}
			if tracker.delete(id) {
				fmt.Println("✅ Удалено")
			} else {
				fmt.Println("❌ Не найдено")
			}
		case "6":
			cats := tracker.listCategories()
			if len(cats) > 0 {
				fmt.Println("Категории:", strings.Join(cats, ", "))
			} else {
				fmt.Println("Нет категорий")
			}
		default:
			fmt.Println("Неверный выбор")
		}
	}
}
