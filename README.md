# sukabumi_culinary
## 1. What you need (tools)

| Tool | Why | Link |
|---|---|---|
| **GitHub account** | Where the project will live | github.com |
| **Git for Windows** | Sends your code to GitHub | git-scm.com |
| **VS Code** (you already have it) | Edit files and use the built-in terminal | |
| *(optional)* **GitHub Desktop** | Easiest way if you do not like commands | desktop.github.com |

## 2. Where to start (in this order)

**Step 1: Set up Git (first time only).** Open a terminal in VS Code (`Ctrl + ~`) and run:
```bash
git config --global user.name "Myat Noe Wai"
git config --global user.email "your-github-email@example.com"
```

**Step 2: Prepare the project folder** (`C:\xampp\htdocs\Thursday`).

- Create `.gitignore` (see below) **before** the first commit.
- Create `config.example.php` from your `config.php` with fake values.
- Put exported SQL in `database/schema.sql` (structure only).
- Put 4 to 6 screenshots in `docs/` (`home.png`, `food-detail.png`, `cart.png`, `map.png`, `events.png`, `assistant.png`).
- Save the README below as `README.md`.

**Step 3: Create the repo on GitHub.** Click **New repository**.
- Name: `sukabumi-culinary`
- Description: *Culinary & tourism web platform for Kabupaten Sukabumi: food catalog, cart & checkout, rewards, events, food map and a built-in recipe assistant. Built with PHP and MySQL.*
- Visibility: **Public**
- Do **not** tick "Add a README" or ".gitignore" (you already have them).

**Step 4: Upload with Git.** In the VS Code terminal, inside the project folder:

```bash
git init
git add .
git status
```
Check the list. `config.php` must **not** appear. If it does:
```bash
git rm --cached config.php
```
Then:
```bash
git commit -m "Initial commit: Sukabumi Culinary Platform"
git branch -M main
git remote add origin https://github.com/YOUR-USERNAME/sukabumi-culinary.git
git push -u origin main
```
A browser window opens once to sign in to GitHub.

**GitHub Desktop alternative:** File → Add local repository → choose the folder → Commit → **Publish repository** (untick "Keep this code private").

**Step 5: Finish the repo page.** On GitHub, click the ⚙️ next to *About* and add:
- Website: `https://sukabumiculinary.infinityfreeapp.com`
- Topics: `php` `mysql` `pdo` `javascript` `leaflet` `ecommerce` `culinary` `tourism` `indonesia` `portfolio-project`

Then pin the repo on your profile and add the link to your CV and LinkedIn.

## 3. `.gitignore`
```
# Secrets and local settings
config.php
mail_config.php
.env

# Member uploads
uploads/avatars/

# System files
.DS_Store
Thumbs.db
*.log
```

## 4. `README.md`
````markdown
# 🍜 Sukabumi Culinary Platform

A culinary and tourism web platform for **Kabupaten Sukabumi, West Java**. Visitors can discover local foods, order them, collect reward points, join events and find food places on a map.

**🌐 Live demo:** https://sukabumiculinary.infinityfreeapp.com

> Taste · Explore · Enjoy

## ✨ Features
- **Culinary catalog** with search, halal filter, sorting, categories and pagination (28 foods)
- **Food detail** with photo gallery, rating, Halal / BPOM badges and reviews
- **Cart and checkout** with coupons, delivery details and payment method selection
- **Order history** with status tracking (Processing → Shipped → Delivered)
- **Foodie Rewards**: earn points on orders, redeem vouchers
- **Wishlist**, **Tourism places**, **Events** with a month calendar
- **Culinary Map** (Leaflet + OpenStreetMap) with filters and "near me"
- **Recipe Assistant**: a chat that suggests foods from the menu in English or Indonesian, no external API
- **Help Center** (searchable FAQ) and **Contact** form
- **Accounts**: register, login, profile photo, change password, password reset with a 6-digit code

## 🔒 Security
- PDO prepared statements and `htmlspecialchars()` output escaping
- Passwords stored with `password_hash()`
- CSRF tokens on sensitive forms
- Reset codes are hashed, expire after 10 minutes, are single-use and rate-limited
- Prices are always read from the database, never from the browser

## 🧰 Tech stack
PHP 8 (PDO) · MySQL / MariaDB · HTML, CSS, vanilla JavaScript · Leaflet · Hosted on InfinityFree

## 🖼️ Screenshots
| Home | Food detail | Cart |
|---|---|---|
| 

![Home](docs/home.png)

 | 

![Food](docs/food-detail.png)

 | 

![Cart](docs/cart.png)

 |

| Map | Events | Assistant |
|---|---|---|
| 

![Map](docs/map.png)

 | 

![Events](docs/events.png)

 | 

![Assistant](docs/assistant.png)

 |

## 🚀 Run locally (XAMPP)
1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Clone the project into `htdocs`:
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/YOUR-USERNAME/sukabumi-culinary.git Thursday
   ```
3. In phpMyAdmin create a database `sukabumi_culinary` and import `database/schema.sql`.
4. Copy `config.example.php` to `config.php` and enter your database details.
5. Create the folders `uploads/main_image`, `uploads/avatars`, `uploads/tourism` and `uploads/food_gallery`.
6. Open `http://localhost/Thursday/`.
<img width="1312" height="1199" alt="WhatsApp Image 2026-11-05 at 9 41 21 PM" src="https://github.com/user-attachments/assets/a2c3be1f-3b87-4a35-94bf-231ffc42c435" />

## 🗺️ Roadmap
- [ ] Admin dashboard (foods, events, orders, users, sales charts)
- [ ] Real payment gateway and email sending (SMTP)
- [ ] Full Indonesian / English / Sundanese translations
- [ ] Review submission and moderation

## 📝 Notes
Payment methods are for demonstration only. Sample foods, places and events are test data.

## 👩‍💻 Author
**Myat Noe Wai**, Information Systems student, Nusa Putra University, Sukabumi.

## 📄 License
For learning and portfolio purposes. Please ask before commercial reuse.
````

## 5. Safety check before you push

- Your Gemini API key was shown in an earlier screenshot, so **delete it** in Google AI Studio if you have not already.
- `config.php` is in `.gitignore` and not on GitHub.
- `database/schema.sql` is **structure only**, with no real users, emails or addresses.

