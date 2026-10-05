# Pharma ERP — Fullstack System (Laravel 11 + Next.js 14)

نظام متكامل لإدارة الصيدليات ومستودعات الأدوية يجمع بين واجهة أمامية حديثة (Next.js 14) وخلفية قوية (Laravel 11 + PostgreSQL).

---

## 📁 هيكل المشروع الموحد (Unified Structure)

```text
pharma-erp/
├── backend/                # Laravel 11 API (المخزون، المبيعات، المشتريات، المحاسبة، الصلاحيات)
├── frontend/               # Next.js 14 UI (الشاشات، لوحات التحكم، الفواتير، التقارير)
├── package.json            # أوامر تشغيل سريعة للمشروعين
├── start-dev.bat           # سكريبت تشغيل فوري بنقرة واحدة (Windows Launcher)
└── README.md
```

---

## 🚀 تشغيل النظام (How to Run)

### الخيار الأسرع (Windows):
فقط اضغط دبل-كليك على الملف:
👉 **`start-dev.bat`**
سيقوم تلقائياً بتشغيل الـ Backend على `http://localhost:8000` والـ Frontend على `http://localhost:3000` وفتح المتصفح فوراً.

### أو يدوياً عبر الـ Terminal:

1. **الباك إند (Backend):**
```bash
cd backend
php artisan serve --port=8000
```

2. **الفرونت إند (Frontend):**
```bash
cd frontend
npm run dev
```

---

## 🗄️ قاعدة البيانات

النظام يدعم **PostgreSQL فقط** (الـ migrations تعتمد على CHECK constraints وأنواع UUID صارمة؛ SQLite غير مدعوم).

```bash
cd backend
cp .env.example .env && php artisan key:generate
# عدّل DB_* في .env ثم:
php artisan migrate --seed
php artisan db:seed --class=SahlAlHadharatSeeder   # بيانات الشركة والفروع والموظفين
```

## 🐳 تشغيل بـ Docker

```bash
cp .env.example .env            # عدّل كلمات المرور
APP_KEY=$(docker compose run --rm backend php artisan key:generate --show) docker compose up --build
```

على Linux/macOS يمكن أيضاً استخدام `./start-dev.sh`.

## 🔑 تسجيل الدخول

اسم المستخدم الافتراضي: `admin`.
كلمة المرور تُحدَّد بالمتغير `SEED_DEFAULT_PASSWORD` في `.env` قبل تشغيل الـ seeder.
في بيئة `local` فقط، إذا لم يُحدَّد، تكون `Passw0rd!`. في أي بيئة أخرى تُولَّد كلمة عشوائية وتُطبع مرة واحدة أثناء الـ seed.

> ⚠️ للإنتاج: استخدم `backend/.env.production.example` (APP_DEBUG=false)، وغيّر كلمات المرور، ولا تكشف النظام للإنترنت بأي كلمة مرور افتراضية.

## ✅ الاختبارات

```bash
cd backend
createdb pharma_erp_test       # مرة واحدة
vendor/bin/phpunit
```

تغطي: تسجيل الدخول والصلاحيات، القيود المحاسبية، حركات المخزون (FEFO، الانتهاء، عدم البيع بدون رصيد)، ونقطة البيع. يعمل CI تلقائياً على GitHub.

## 💱 العملات (دينار / دولار)

العملة الأساسية للدفاتر هي **الدينار العراقي (IQD)**، والعملة الأجنبية الوحيدة المدعومة **الدولار (USD)** بسعر صرف يُدخَل **يدوياً كل يوم** من `الحسابات العامة ← أسعار الصرف (USD)` (أو `POST /api/v1/exchange-rates`، صلاحية `finance.manage`).

- فواتير البيع والشراء والمدفوعات (عملاء/موردين) تقبل `currency` = `IQD` أو `USD`. المستند يأخذ سعر يوم تاريخه، وإذا فات يوم يُستخدم آخر سعر سابق. لا يوجد أي سعر = يُرفض الإصدار (لا يُخمَّن السعر).
- القيود المحاسبية تُرحَّل دائماً بالدينار. دفعة بالدولار لا تُسدَّد على فاتورة بالدينار والعكس.
- عند السداد يُسجَّل **فرق صرف محقق** تلقائياً في حساب `FX_GAIN_LOSS` (ربح أو خسارة بين سعر الفاتورة وسعر الدفعة).
- التقارير والـ dashboard وأرصدة العملاء والموردين تُحسب بالدينار.
- حدود حالية: لا يوجد تقييم لأرصدة نهاية الفترة (unrealized FX)، والمخزون يُقيَّم بالدينار، ونقطة البيع (POS) بالدينار فقط. فاتورة شراء بالدولار مربوطة باستلام بضاعة تُحاسَب على حساب GRNI بسعر الفاتورة.

## 💾 النسخ الاحتياطي

`php artisan backup:database` ينشئ نسخة `pg_dump` في `backend/storage/app/backups` ويحذف الأقدم من 14 يوماً. مجدول يومياً 02:00 (يحتاج `php artisan schedule:work` أو cron).
