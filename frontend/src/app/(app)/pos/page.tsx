"use client";

import { useEffect, useState, useMemo, useRef } from "react";
import { apiFetch } from "@/lib/api";
import {
  Search,
  Barcode,
  ShoppingCart,
  Plus,
  Minus,
  Trash2,
  Printer,
  CheckCircle2,
  AlertTriangle,
  User,
  DollarSign,
  CreditCard,
  Wallet,
  FileText,
  Sparkles,
  Layers,
  ShieldAlert,
  X,
  Receipt,
  RotateCcw,
  Package,
} from "lucide-react";
import { PermissionGate } from "@/components/layout/permission-gate";
import { PERMISSIONS } from "@/lib/permissions";

interface PosProduct {
  id: string;
  code: string;
  barcode: string;
  name: string;
  generic_name: string | null;
  form: string | null;
  strength: string | null;
  category_name: string;
  is_controlled_substance: boolean;
  requires_prescription: boolean;
  purchase_price: number;
  wholesale_price: number;
  sale_price: number;
  profit_margin_retail: number;
  profit_margin_wholesale: number;
  total_stock: number;
  batches: Array<{
    id: string;
    batch_number: string;
    expiry_date: string;
    quantity: number;
    days_to_expiry: number;
  }>;
}

interface CartItem {
  product: PosProduct;
  quantity: number;
  unit_price: number;
  batch_id?: string;
}

export default function PosPage() {
  const [products, setProducts] = useState<PosProduct[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Filters & Search
  const [search, setSearch] = useState("");
  const [selectedCategory, setSelectedCategory] = useState<string>("ALL");
  const barcodeInputRef = useRef<HTMLInputElement>(null);

  // Cart & Pricing
  const [cart, setCart] = useState<CartItem[]>([]);
  const [pricingTier, setPricingTier] = useState<"retail" | "wholesale">("retail");
  const [paymentMethod, setPaymentMethod] = useState<"cash" | "card" | "debt">("cash");
  const [customerName, setCustomerName] = useState<string>("");
  const [discountAmount, setDiscountAmount] = useState<number>(0);
  const [checkingOut, setCheckingOut] = useState(false);

  // Prescription Modal for Controlled Drugs
  const [rxModalOpen, setRxModalOpen] = useState(false);
  const [rxData, setRxData] = useState({
    patient_name: "",
    patient_national_id: "",
    doctor_name: "",
    doctor_syndicate_id: "",
    prescription_number: "",
    diagnosis_notes: "",
  });

  // Receipt Modal
  const [receipt, setReceipt] = useState<any | null>(null);

  // Multi-tier price edit modal
  const [editProduct, setEditProduct] = useState<PosProduct | null>(null);
  const [editPrices, setEditPrices] = useState({ cost: 0, wholesale: 0, retail: 0 });
  const [savingPrice, setSavingPrice] = useState(false);

  // Load products
  const fetchProducts = async () => {
    try {
      setLoading(true);
      const res: any = await apiFetch("/v1/pos/products");
      setProducts(res.data || []);
      setError(null);
    } catch (err: any) {
      setError(err?.message || "تعذر تحميل قائمة الأدوية لنقطة البيع.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProducts();
  }, []);

  // Update item unit prices when tier changes
  useEffect(() => {
    setCart((prevCart) =>
      prevCart.map((item) => ({
        ...item,
        unit_price:
          pricingTier === "wholesale"
            ? item.product.wholesale_price
            : item.product.sale_price,
      }))
    );
  }, [pricingTier]);

  // Categories list
  const categories = useMemo(() => {
    const set = new Set<string>();
    products.forEach((p) => {
      if (p.category_name) set.add(p.category_name);
    });
    return ["ALL", ...Array.from(set)];
  }, [products]);

  // Filtered Products
  const filteredProducts = useMemo(() => {
    return products.filter((p) => {
      const matchCat =
        selectedCategory === "ALL" ||
        (selectedCategory === "CONTROLLED" && p.is_controlled_substance) ||
        p.category_name === selectedCategory;

      const q = search.trim().toLowerCase();
      const matchSearch =
        !q ||
        p.name.toLowerCase().includes(q) ||
        (p.generic_name && p.generic_name.toLowerCase().includes(q)) ||
        (p.barcode && p.barcode.toLowerCase().includes(q)) ||
        p.code.toLowerCase().includes(q);

      return matchCat && matchSearch;
    });
  }, [products, selectedCategory, search]);

  // Add to Cart
  const addToCart = (product: PosProduct) => {
    if (product.total_stock <= 0) {
      alert("هذا الدواء نفد من المخزون حالياً!");
      return;
    }

    setCart((prev) => {
      const existing = prev.find((item) => item.product.id === product.id);
      const price =
        pricingTier === "wholesale"
          ? product.wholesale_price
          : product.sale_price;

      if (existing) {
        if (existing.quantity >= product.total_stock) {
          alert(`لا توجد كمية كافية بالمخزون! المتاح: ${product.total_stock}`);
          return prev;
        }
        return prev.map((item) =>
          item.product.id === product.id
            ? { ...item, quantity: item.quantity + 1 }
            : item
        );
      }
      return [...prev, { product, quantity: 1, unit_price: price }];
    });
  };

  // Barcode enter handler
  const handleBarcodeSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!search.trim()) return;

    const found = products.find(
      (p) =>
        (p.barcode && p.barcode.toLowerCase() === search.trim().toLowerCase()) ||
        p.code.toLowerCase() === search.trim().toLowerCase()
    );

    if (found) {
      addToCart(found);
      setSearch("");
    }
  };

  const updateQuantity = (productId: string, delta: number) => {
    setCart((prev) =>
      prev
        .map((item) => {
          if (item.product.id === productId) {
            const newQty = item.quantity + delta;
            if (newQty > item.product.total_stock) {
              alert(`أقصى كمية متاحة هي: ${item.product.total_stock}`);
              return item;
            }
            return newQty > 0 ? { ...item, quantity: newQty } : null;
          }
          return item;
        })
        .filter(Boolean) as CartItem[]
    );
  };

  const removeFromCart = (productId: string) => {
    setCart((prev) => prev.filter((item) => item.product.id !== productId));
  };

  // Cart Calculations
  const subtotal = useMemo(() => {
    return cart.reduce((sum, item) => sum + item.quantity * item.unit_price, 0);
  }, [cart]);

  const grandTotal = Math.max(0, subtotal - discountAmount);

  // Check if cart has controlled substance
  const hasControlledSubstance = useMemo(() => {
    return cart.some(
      (item) =>
        item.product.is_controlled_substance ||
        item.product.requires_prescription
    );
  }, [cart]);

  // Checkout process
  const handleCheckoutClick = () => {
    if (cart.length === 0) {
      alert("السلة فارغة!");
      return;
    }

    if (hasControlledSubstance) {
      // Require prescription details
      setRxModalOpen(true);
      return;
    }

    executeCheckout();
  };

  const executeCheckout = async (prescriptionOverride?: any) => {
    try {
      setCheckingOut(true);
      const payload = {
        items: cart.map((it) => ({
          product_id: it.product.id,
          quantity: it.quantity,
          unit_price: it.unit_price,
          batch_id: it.batch_id || null,
        })),
        pricing_tier: pricingTier,
        payment_method: paymentMethod,
        customer_name: customerName.trim() || "زبون نقدي مباشر",
        discount_amount: discountAmount,
        prescription: prescriptionOverride || (hasControlledSubstance ? rxData : null),
      };

      const res: any = await apiFetch("/v1/pos/checkout", {
        method: "POST",
        body: payload,
      });

      if (res.status === "success") {
        setReceipt(res.data.receipt);
        setCart([]);
        setDiscountAmount(0);
        setCustomerName("");
        setRxModalOpen(false);
        fetchProducts(); // Refresh stock
      }
    } catch (err: any) {
      alert(err?.message || "حدث خطأ أثناء إتمام عملية البيع.");
    } finally {
      setCheckingOut(false);
    }
  };

  // Submit Rx Modal
  const handleRxSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!rxData.doctor_name || !rxData.prescription_number || !rxData.patient_name) {
      alert("يرجى ملء جميع الحقول الإلزامية للوصفة الطبية (اسم الطبيب، رقم الوصفة، اسم المريض).");
      return;
    }
    executeCheckout(rxData);
  };

  // Save Pricing Tier Edit
  const handleSavePrices = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editProduct) return;

    try {
      setSavingPrice(true);
      await apiFetch(`/v1/pos/products/${editProduct.id}/price`, {
        method: "PUT",
        body: {
          purchase_price: editPrices.cost,
          wholesale_price: editPrices.wholesale,
          sale_price: editPrices.retail,
        },
      });
      setEditProduct(null);
      fetchProducts();
    } catch (err: any) {
      alert(err?.message || "تعذر حفظ الأسعار.");
    } finally {
      setSavingPrice(false);
    }
  };

  return (
    <PermissionGate permission={PERMISSIONS.salesCreate}>
      <div className="flex flex-col lg:flex-row gap-5 min-h-[calc(100vh-100px)] text-slate-800 dark:text-slate-100" dir="rtl">
      
      {/* LEFT: Product Catalog & Search (65% width) */}
      <div className="flex-1 flex flex-col bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm overflow-hidden">
        
        {/* Top Search & Barcode Scanner Header */}
        <div className="flex flex-col sm:flex-row items-center gap-3 mb-4">
          <form onSubmit={handleBarcodeSubmit} className="relative flex-1 w-full">
            <Search className="absolute right-4 top-3.5 w-5 h-5 text-slate-400" />
            <input
              ref={barcodeInputRef}
              type="text"
              placeholder="امسح الباركود أو ابحث عن الدواء (الاسم التجاري، العلمي، الكود)..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-4 pr-12 py-3 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 font-medium transition"
            />
            <button
              type="submit"
              className="absolute left-2.5 top-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs px-3 py-1.5 rounded-xl font-bold transition flex items-center gap-1.5 shadow"
            >
              <Barcode className="w-4 h-4" />
              <span>إدخال</span>
            </button>
          </form>

          {/* Pricing Tier Selector (Retail / Wholesale) */}
          <div className="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 p-1.5 rounded-2xl border border-slate-200 dark:border-slate-700 w-full sm:w-auto shrink-0">
            <button
              type="button"
              onClick={() => setPricingTier("retail")}
              className={`flex-1 sm:flex-none px-4 py-2 rounded-xl text-xs font-bold transition ${
                pricingTier === "retail"
                  ? "bg-emerald-600 text-white shadow-sm"
                  : "text-slate-600 dark:text-slate-400 hover:text-slate-900"
              }`}
            >
              مفرد (Retail)
            </button>
            <button
              type="button"
              onClick={() => setPricingTier("wholesale")}
              className={`flex-1 sm:flex-none px-4 py-2 rounded-xl text-xs font-bold transition ${
                pricingTier === "wholesale"
                  ? "bg-cyan-600 text-white shadow-sm"
                  : "text-slate-600 dark:text-slate-400 hover:text-slate-900"
              }`}
            >
              جملة (Wholesale)
            </button>
          </div>
        </div>

        {/* Categories Quick Filter Pills */}
        <div className="flex items-center gap-2 overflow-x-auto pb-3 mb-2 no-scrollbar">
          <button
            onClick={() => setSelectedCategory("ALL")}
            className={`px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition ${
              selectedCategory === "ALL"
                ? "bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900"
                : "bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200"
            }`}
          >
            الكل ({products.length})
          </button>
          <button
            onClick={() => setSelectedCategory("CONTROLLED")}
            className={`px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition flex items-center gap-1.5 ${
              selectedCategory === "CONTROLLED"
                ? "bg-rose-600 text-white"
                : "bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900/60"
            }`}
          >
            <ShieldAlert className="w-3.5 h-3.5" />
            أدوية مراقبة بوصفة
          </button>
          {categories
            .filter((c) => c !== "ALL")
            .map((cat) => (
              <button
                key={cat}
                onClick={() => setSelectedCategory(cat)}
                className={`px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition ${
                  selectedCategory === cat
                    ? "bg-emerald-600 text-white"
                    : "bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200"
                }`}
              >
                {cat}
              </button>
            ))}
        </div>

        {/* Product Cards Grid */}
        <div className="flex-1 overflow-y-auto pr-1">
          {loading ? (
            <div className="h-64 flex items-center justify-center text-slate-400 font-bold">
              جاري تحميل الأدوية...
            </div>
          ) : filteredProducts.length === 0 ? (
            <div className="h-64 flex flex-col items-center justify-center text-slate-400 gap-2">
              <Package className="w-10 h-10 opacity-40" />
              <p>لم يتم العثور على أدوية مطابقة للبحث.</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
              {filteredProducts.map((p) => {
                const activePrice =
                  pricingTier === "wholesale" ? p.wholesale_price : p.sale_price;
                const activeMargin =
                  pricingTier === "wholesale"
                    ? p.profit_margin_wholesale
                    : p.profit_margin_retail;

                const inStock = p.total_stock > 0;
                const earliestBatch = p.batches[0];

                return (
                  <div
                    key={p.id}
                    onClick={() => inStock && addToCart(p)}
                    className={`group relative bg-slate-50 dark:bg-slate-800/60 border rounded-2xl p-4 flex flex-col justify-between transition-all duration-150 ${
                      inStock
                        ? "cursor-pointer hover:border-emerald-500 hover:shadow-md active:scale-[0.98]"
                        : "opacity-60 cursor-not-allowed border-dashed border-slate-300 dark:border-slate-700"
                    } ${
                      p.is_controlled_substance
                        ? "border-rose-400/40 dark:border-rose-900/60"
                        : "border-slate-200 dark:border-slate-700/80"
                    }`}
                  >
                    <div>
                      {/* Top Badges */}
                      <div className="flex items-center justify-between mb-2">
                        <span className="text-[10px] font-mono font-bold bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 px-2 py-0.5 rounded-md">
                          {p.barcode || p.code}
                        </span>
                        {p.is_controlled_substance && (
                          <span className="text-[9px] font-bold bg-rose-600 text-white px-2 py-0.5 rounded-full flex items-center gap-1 shadow-sm">
                            <ShieldAlert className="w-2.5 h-2.5" />
                            مراقب
                          </span>
                        )}
                      </div>

                      {/* Name & Details */}
                      <h3 className="font-extrabold text-sm text-slate-900 dark:text-white line-clamp-1 group-hover:text-emerald-500 transition">
                        {p.name}
                      </h3>
                      {p.generic_name && (
                        <p className="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-1 italic">
                          {p.generic_name}
                        </p>
                      )}
                      <p className="text-[10px] text-slate-400 mt-1">
                        {p.form} • {p.strength}
                      </p>
                    </div>

                    {/* Stock & Batch Expiry */}
                    <div className="mt-3 pt-2.5 border-t border-slate-200/80 dark:border-slate-700/60 flex items-center justify-between text-xs">
                      <div>
                        <span className="text-[10px] text-slate-400 block">المتوفر:</span>
                        <span
                          className={`font-black text-xs ${
                            p.total_stock <= 5
                              ? "text-rose-500"
                              : "text-emerald-600 dark:text-emerald-400"
                          }`}
                        >
                          {p.total_stock} عبوة
                        </span>
                      </div>

                      {earliestBatch && (
                        <div className="text-left">
                          <span className="text-[9px] text-slate-400 block">الصلاحية:</span>
                          <span
                            className={`text-[10px] font-mono font-bold ${
                              earliestBatch.days_to_expiry <= 30
                                ? "text-rose-500"
                                : "text-slate-500 dark:text-slate-400"
                            }`}
                          >
                            {earliestBatch.expiry_date}
                          </span>
                        </div>
                      )}
                    </div>

                    {/* Pricing & Profit Margin Footer */}
                    <div className="mt-2.5 pt-2 flex items-center justify-between">
                      <div>
                        <div className="flex items-baseline gap-1">
                          <span className="text-base font-black text-slate-900 dark:text-white">
                            {activePrice.toLocaleString()}
                          </span>
                          <span className="text-[10px] text-slate-400">د.ع</span>
                        </div>
                        <span className="text-[9px] text-emerald-600 dark:text-emerald-400 font-bold block">
                          ربح: +{activeMargin}%
                        </span>
                      </div>

                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          setEditProduct(p);
                          setEditPrices({
                            cost: p.purchase_price,
                            wholesale: p.wholesale_price,
                            retail: p.sale_price,
                          });
                        }}
                        title="تعديل مستويات الأسعار"
                        className="text-[10px] text-slate-400 hover:text-cyan-500 p-1.5 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-700 transition"
                      >
                        <Layers className="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>

      {/* RIGHT: Active Cashier Order Cart & Checkout Panel (35% width) */}
      <div className="w-full lg:w-[420px] bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-xl flex flex-col justify-between shrink-0">
        <div>
          {/* Cart Header */}
          <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
            <div className="flex items-center gap-2">
              <div className="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                <ShoppingCart className="w-5 h-5" />
              </div>
              <div>
                <h2 className="font-extrabold text-sm text-slate-900 dark:text-white">
                  سلة المبيعات
                </h2>
                <span className="text-[11px] text-slate-400">
                  {cart.length} أصناف في الفاتورة
                </span>
              </div>
            </div>

            {cart.length > 0 && (
              <button
                onClick={() => setCart([])}
                className="text-xs text-rose-500 hover:text-rose-600 font-bold flex items-center gap-1 transition"
              >
                <RotateCcw className="w-3.5 h-3.5" />
                تفريغ
              </button>
            )}
          </div>

          {/* Customer Input */}
          <div className="mt-3">
            <div className="relative">
              <User className="absolute right-3 top-3 w-4 h-4 text-slate-400" />
              <input
                type="text"
                placeholder="اسم العميل (افتراضي: زبون نقدي مباشر)..."
                value={customerName}
                onChange={(e) => setCustomerName(e.target.value)}
                className="w-full pr-9 pl-3 py-2 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-emerald-500"
              />
            </div>
          </div>

          {/* Controlled Drug Warning Banner */}
          {hasControlledSubstance && (
            <div className="mt-3 p-2.5 bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-900/80 rounded-2xl flex items-center gap-2.5 text-xs text-rose-700 dark:text-rose-300 animate-pulse">
              <ShieldAlert className="w-5 h-5 text-rose-600 shrink-0" />
              <span>
                <strong>تحذير رقابي:</strong> الفاتورة تتطلب وصفة طبية مسجلة ومعتمدة.
              </span>
            </div>
          )}

          {/* Cart Items List */}
          <div className="mt-3 max-h-[300px] overflow-y-auto space-y-2 pr-1">
            {cart.length === 0 ? (
              <div className="py-12 text-center text-slate-400 text-xs">
                <ShoppingCart className="w-8 h-8 mx-auto opacity-30 mb-2" />
                السلة فارغة. اختر أدوية أو امسح الباركود للبدء.
              </div>
            ) : (
              cart.map((item) => (
                <div
                  key={item.product.id}
                  className="bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 rounded-2xl p-2.5 flex items-center justify-between gap-2"
                >
                  <div className="flex-1 min-w-0">
                    <h4 className="text-xs font-bold text-slate-900 dark:text-white truncate">
                      {item.product.name}
                    </h4>
                    <span className="text-[10px] text-slate-400">
                      {item.unit_price.toLocaleString()} د.ع × {item.quantity}
                    </span>
                  </div>

                  {/* Quantity Controls */}
                  <div className="flex items-center gap-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-1">
                    <button
                      onClick={() => updateQuantity(item.product.id, -1)}
                      className="w-5 h-5 flex items-center justify-center text-slate-500 hover:text-slate-900 dark:hover:text-white"
                    >
                      <Minus className="w-3 h-3" />
                    </button>
                    <span className="font-mono font-bold text-xs px-1">
                      {item.quantity}
                    </span>
                    <button
                      onClick={() => updateQuantity(item.product.id, 1)}
                      className="w-5 h-5 flex items-center justify-center text-slate-500 hover:text-slate-900 dark:hover:text-white"
                    >
                      <Plus className="w-3 h-3" />
                    </button>
                  </div>

                  <span className="font-black text-xs text-slate-800 dark:text-slate-200 shrink-0">
                    {(item.quantity * item.unit_price).toLocaleString()}
                  </span>

                  <button
                    onClick={() => removeFromCart(item.product.id)}
                    className="text-slate-400 hover:text-rose-500 p-1 transition"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              ))
            )}
          </div>
        </div>

        {/* Bottom Payment & Grand Totals */}
        <div className="mt-4 pt-4 border-t border-slate-200 dark:border-slate-800 space-y-3">
          
          {/* Payment Method Switcher */}
          <div className="grid grid-cols-3 gap-2">
            <button
              type="button"
              onClick={() => setPaymentMethod("cash")}
              className={`py-2 px-1 rounded-xl text-xs font-bold flex flex-col items-center gap-1 border transition ${
                paymentMethod === "cash"
                  ? "bg-emerald-600 text-white border-emerald-600 shadow"
                  : "bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700"
              }`}
            >
              <DollarSign className="w-4 h-4" />
              <span>نقدي (Cash)</span>
            </button>
            <button
              type="button"
              onClick={() => setPaymentMethod("card")}
              className={`py-2 px-1 rounded-xl text-xs font-bold flex flex-col items-center gap-1 border transition ${
                paymentMethod === "card"
                  ? "bg-cyan-600 text-white border-cyan-600 shadow"
                  : "bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700"
              }`}
            >
              <CreditCard className="w-4 h-4" />
              <span>بطاقة (Card)</span>
            </button>
            <button
              type="button"
              onClick={() => setPaymentMethod("debt")}
              className={`py-2 px-1 rounded-xl text-xs font-bold flex flex-col items-center gap-1 border transition ${
                paymentMethod === "debt"
                  ? "bg-amber-600 text-white border-amber-600 shadow"
                  : "bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700"
              }`}
            >
              <Wallet className="w-4 h-4" />
              <span>آجل (Debt)</span>
            </button>
          </div>

          {/* Discount & Totals */}
          <div className="space-y-1.5 text-xs text-slate-500 dark:text-slate-400">
            <div className="flex justify-between">
              <span>المجموع الفرعي:</span>
              <span className="font-mono font-bold text-slate-800 dark:text-slate-200">
                {subtotal.toLocaleString()} د.ع
              </span>
            </div>

            <div className="flex items-center justify-between">
              <span>خصم (تخفيض):</span>
              <input
                type="number"
                min="0"
                value={discountAmount}
                onChange={(e) => setDiscountAmount(Math.max(0, Number(e.target.value)))}
                className="w-24 px-2 py-1 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-right font-mono font-bold text-xs"
              />
            </div>

            <div className="pt-2 border-t border-slate-200 dark:border-slate-800 flex justify-between items-baseline">
              <span className="text-sm font-extrabold text-slate-900 dark:text-white">
                الإجمالي المستحق:
              </span>
              <div className="text-right">
                <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                  {grandTotal.toLocaleString()}
                </span>
                <span className="text-xs text-slate-400 mr-1 font-bold">د.ع</span>
              </div>
            </div>
          </div>

          {/* Checkout Action Button */}
          <button
            onClick={handleCheckoutClick}
            disabled={cart.length === 0 || checkingOut}
            className={`w-full py-4 rounded-2xl font-black text-base shadow-xl transition flex items-center justify-center gap-2 ${
              cart.length === 0 || checkingOut
                ? "bg-slate-300 dark:bg-slate-800 text-slate-500 cursor-not-allowed"
                : "bg-emerald-600 hover:bg-emerald-500 text-white active:scale-98"
            }`}
          >
            <CheckCircle2 className="w-5 h-5" />
            <span>
              {checkingOut
                ? "جاري إتمام الفاتورة..."
                : hasControlledSubstance
                ? "متابعة بيانات الوصفة وإصدار الفاتورة"
                : "إتمام الدفع وطباعة الفاتورة"}
            </span>
          </button>
        </div>
      </div>

      {/* MODAL 1: Controlled Drug Prescription Intake Form */}
      {rxModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2 text-rose-600">
                <ShieldAlert className="w-6 h-6" />
                <h3 className="font-black text-base">تسجيل الوصفة الطبية (أدوية مراقبة)</h3>
              </div>
              <button onClick={() => setRxModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                <X className="w-5 h-5" />
              </button>
            </div>

            <p className="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
              وفق تعليمات وزارة الصحة ونقابة الصيادلة، يجب تسجيل بيانات الوصفة الطبية بدقة قبل صرف أي دواء خاضع للرقابة.
            </p>

            <form onSubmit={handleRxSubmit} className="space-y-3 text-xs">
              <div>
                <label className="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                  اسم المريض الرباعي *
                </label>
                <input
                  type="text"
                  required
                  value={rxData.patient_name}
                  onChange={(e) => setRxData({ ...rxData, patient_name: e.target.value })}
                  placeholder="محمد أحمد عبد الله..."
                  className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border rounded-xl"
                />
              </div>

              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                    الرقم الوطني / الهوية
                  </label>
                  <input
                    type="text"
                    value={rxData.patient_national_id}
                    onChange={(e) => setRxData({ ...rxData, patient_national_id: e.target.value })}
                    placeholder="1990123456..."
                    className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border rounded-xl"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                    رقم الوصفة الطبية *
                  </label>
                  <input
                    type="text"
                    required
                    value={rxData.prescription_number}
                    onChange={(e) => setRxData({ ...rxData, prescription_number: e.target.value })}
                    placeholder="RX-2026-991..."
                    className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border rounded-xl font-mono"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                    اسم الطبيب المعالج *
                  </label>
                  <input
                    type="text"
                    required
                    value={rxData.doctor_name}
                    onChange={(e) => setRxData({ ...rxData, doctor_name: e.target.value })}
                    placeholder="د. عمر خالد..."
                    className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border rounded-xl"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                    رقم هوية النقابة
                  </label>
                  <input
                    type="text"
                    value={rxData.doctor_syndicate_id}
                    onChange={(e) => setRxData({ ...rxData, doctor_syndicate_id: e.target.value })}
                    placeholder="SYND-54321..."
                    className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border rounded-xl font-mono"
                  />
                </div>
              </div>

              <div>
                <label className="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                  ملاحظات التشخيص أو تعليمات الجرعة
                </label>
                <textarea
                  rows={2}
                  value={rxData.diagnosis_notes}
                  onChange={(e) => setRxData({ ...rxData, diagnosis_notes: e.target.value })}
                  placeholder="حسب الوصفة المرفقة..."
                  className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border rounded-xl"
                />
              </div>

              <div className="pt-2 flex gap-2">
                <button
                  type="submit"
                  disabled={checkingOut}
                  className="flex-1 py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-bold rounded-xl shadow transition"
                >
                  {checkingOut ? "جاري الحفظ..." : "تأكيد وصرف الأدوية"}
                </button>
                <button
                  type="button"
                  onClick={() => setRxModalOpen(false)}
                  className="px-4 py-2.5 bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold rounded-xl"
                >
                  إلغاء
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 2: 80mm Thermal POS Receipt Printable View */}
      {receipt && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white text-black rounded-3xl max-w-sm w-full p-6 shadow-2xl relative font-mono text-xs">
            <button
              onClick={() => setReceipt(null)}
              className="absolute left-4 top-4 text-gray-500 hover:text-black print:hidden"
            >
              <X className="w-5 h-5" />
            </button>

            {/* Printable Receipt Area */}
            <div id="thermal-receipt" className="text-center space-y-3">
              <div className="border-b-2 border-dashed border-gray-400 pb-3">
                <h2 className="text-lg font-black tracking-tight">شركة سهل الحضارات للأدوية البيطرية</h2>
                <p className="text-[11px] text-gray-600">تجارة وتوزيع الأدوية والمستلزمات البيطرية</p>
                <p className="text-[10px] text-gray-500">هاتف: 07701112233 • الرقم الضريبي: VET-SAHL-2024</p>
              </div>

              <div className="text-right text-[11px] space-y-1 border-b border-dashed border-gray-300 pb-2">
                <div>رقم الفاتورة: <strong>{receipt.invoice_number}</strong></div>
                <div>التاريخ: {receipt.date}</div>
                <div>الكاشير: {receipt.cashier}</div>
                <div>العميل: {receipt.customer}</div>
                <div>التسعيرة: {receipt.pricing_tier}</div>
                <div>طريقة الدفع: {receipt.payment_method}</div>
              </div>

              {/* Items Table */}
              <div className="text-right space-y-1.5 border-b-2 border-dashed border-gray-400 pb-3">
                <div className="flex justify-between font-bold text-[11px] border-b pb-1">
                  <span>الصنف</span>
                  <span>الكمية × السعر</span>
                  <span>المجموع</span>
                </div>
                {receipt.items?.map((it: any, i: number) => (
                  <div key={i} className="flex justify-between text-[10px]">
                    <span className="truncate max-w-[130px]">{it.name}</span>
                    <span>{it.quantity} × {it.unit_price.toLocaleString()}</span>
                    <span className="font-bold">{it.subtotal.toLocaleString()}</span>
                  </div>
                ))}
              </div>

              {/* Totals */}
              <div className="text-right space-y-1 pt-1 font-bold text-[11px]">
                <div className="flex justify-between">
                  <span>المجموع الفرعي:</span>
                  <span>{receipt.subtotal?.toLocaleString()} د.ع</span>
                </div>
                {receipt.discount > 0 && (
                  <div className="flex justify-between text-red-600">
                    <span>الخصم:</span>
                    <span>-{receipt.discount?.toLocaleString()} د.ع</span>
                  </div>
                )}
                <div className="flex justify-between text-sm border-t pt-1 font-black">
                  <span>الصافي المدفوع:</span>
                  <span>{receipt.total?.toLocaleString()} د.ع</span>
                </div>
              </div>

              {/* Barcode & Footer */}
              <div className="pt-3 border-t border-dashed border-gray-300 space-y-1 text-center">
                <p className="text-[10px] text-gray-600 font-bold">شكراً لتعاملكم مع شركة سهل الحضارات</p>
                <div className="text-[9px] text-gray-400 font-mono">نظام سهل الحضارات ERP البيطري</div>
              </div>
            </div>

            {/* Print Action Buttons */}
            <div className="mt-5 pt-3 border-t flex gap-2 print:hidden">
              <button
                onClick={() => window.print()}
                className="flex-1 py-2.5 bg-black hover:bg-gray-800 text-white font-bold rounded-xl flex items-center justify-center gap-1.5 transition"
              >
                <Printer className="w-4 h-4" />
                <span>طباعة الوصل الحراري</span>
              </button>
              <button
                onClick={() => setReceipt(null)}
                className="px-4 py-2.5 bg-gray-200 text-gray-800 font-bold rounded-xl"
              >
                إغلاق
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 3: Edit Multi-Tier Pricing Modal */}
      {editProduct && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2 text-cyan-600 dark:text-cyan-400">
                <Layers className="w-5 h-5" />
                <h3 className="font-black text-sm">مستويات الأسعار وهامش الربح</h3>
              </div>
              <button onClick={() => setEditProduct(null)} className="text-slate-400 hover:text-slate-600">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="text-xs">
              <h4 className="font-extrabold text-base text-slate-900 dark:text-white">{editProduct.name}</h4>
              <p className="text-slate-400">{editProduct.form} • {editProduct.strength}</p>
            </div>

            <form onSubmit={handleSavePrices} className="space-y-3 text-xs">
              <div>
                <label className="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                  1. سعر الشراء (التكلفة)
                </label>
                <input
                  type="number"
                  min="0"
                  step="0.01"
                  value={editPrices.cost}
                  onChange={(e) => setEditPrices({ ...editPrices, cost: Number(e.target.value) })}
                  className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border rounded-xl font-mono font-bold"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                  2. سعر بيع الجملة (Wholesale Price)
                </label>
                <input
                  type="number"
                  min="0"
                  step="0.01"
                  value={editPrices.wholesale}
                  onChange={(e) => setEditPrices({ ...editPrices, wholesale: Number(e.target.value) })}
                  className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border rounded-xl font-mono font-bold text-cyan-500"
                />
                <span className="text-[10px] text-slate-400 block mt-0.5">
                  هامش ربح الجملة: {editPrices.cost > 0 ? (((editPrices.wholesale - editPrices.cost) / editPrices.cost) * 100).toFixed(1) : 0}%
                </span>
              </div>

              <div>
                <label className="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                  3. سعر بيع المفرد (Retail Price) *
                </label>
                <input
                  type="number"
                  min="0"
                  step="0.01"
                  required
                  value={editPrices.retail}
                  onChange={(e) => setEditPrices({ ...editPrices, retail: Number(e.target.value) })}
                  className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border rounded-xl font-mono font-bold text-emerald-500"
                />
                <span className="text-[10px] text-slate-400 block mt-0.5">
                  هامش ربح المفرد: {editPrices.cost > 0 ? (((editPrices.retail - editPrices.cost) / editPrices.cost) * 100).toFixed(1) : 0}%
                </span>
              </div>

              <div className="pt-3 flex gap-2">
                <button
                  type="submit"
                  disabled={savingPrice}
                  className="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow transition"
                >
                  {savingPrice ? "جاري الحفظ..." : "حفظ التسعيرات الجديدة"}
                </button>
                <button
                  type="button"
                  onClick={() => setEditProduct(null)}
                  className="px-4 py-2.5 bg-slate-200 dark:bg-slate-800 font-bold rounded-xl"
                >
                  إلغاء
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      </div>
    </PermissionGate>
  );
}

