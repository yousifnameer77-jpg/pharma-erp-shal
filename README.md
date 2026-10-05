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

## 🔑 بيانات تسجيل الدخول الافتراضية (Default Credentials)

| الحقل | القيمة |
|---|---|
| **اسم المستخدم (Username)** | `admin` |
| **كلمة المرور (Password)** | `Passw0rd!` |
| **الرابط المباشر (Frontend)** | [http://localhost:3000](http://localhost:3000) |
| **واجهة الـ API** | [http://localhost:8000/api/v1](http://localhost:8000/api/v1) |

