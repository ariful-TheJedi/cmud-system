import AppLayout from "@/layouts/app-layout";
import { useApi, ApiRequestError } from "@/hooks/use-api";
import { Head, usePage } from "@inertiajs/react";
import {
    CreditCard,
    Eye,
    LoaderCircle,
    Plus,
    Printer,
    ReceiptText,
    Search,
} from "lucide-react";
import {
    useCallback,
    useEffect,
    useState,
    type FormEvent,
} from "react";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import type { Auth } from "@/types/auth";

type Student = {
    id: number;
    student_code: string;
    full_name: string;
    phone?: string | null;
    email?: string | null;
};
type Enrollment = {
    id: number;
    enrollment_code: string;
    enrollment_date: string;
    status: string;
    course: { id: number; code: string; name: string } | null;
    batch: { id: number; code: string; name: string } | null;
    branch: { id: number; name: string } | null;
    stage_records: {
        id: number;
        status: string;
        course_stage: { id: number; code: string; name: string } | null;
    }[];
    financial_status?: string | null;
};
type Payment = {
    id: number;
    payment_number: string;
    amount: string;
    paid_at: string;
    status: string;
    payment_method?: string | null;
    transaction_reference?: string | null;
    notes?: string | null;
    recorded_by_name?: string | null;
    source?: "tuition" | "expense";
};
type ExpenseOption = {
    id: number;
    code: string;
    name: string;
    description?: string | null;
    price: {
        id: number;
        amount: string;
    };
};
type PaymentOptions = {
    payment_methods: {
        id: number;
        code: string;
        name: string;
        requires_transaction_reference: boolean;
        requires_sender_phone: boolean;
    }[];
};
type BillingOptions = {
    student: Student;
    enrollments: (Enrollment & {
        payment_methods: PaymentOptions["payment_methods"];
        expense_items: ExpenseOption[];
    })[];
};
type Envelope<T> = { data: T; message?: string };
type RegisterPaymentRecordApi = {
    id: number;
    payment_key: string;
    source: "tuition" | "expense";
    payment_number: string;
    amount: string;
    paid_at: string;
    status: string;
    payment_method: string | null;
    transaction_reference: string | null;
    payer_phone?: string | null;
    notes?: string | null;
    recorded_by_name: string | null;
    category: string;
    description: string;
    expense_id: number | null;
    student: Student;
    enrollment: {
        id: number | null;
        enrollment_code: string | null;
        course_name: string | null;
        batch_name: string | null;
        branch_name: string | null;
        branch_phone: string | null;
        branch_email: string | null;
        branch_address: string | null;
    };
};
type RegisterPaymentRecord = Payment & {
    payment_key: string;
    category: string;
    description: string;
    expense_id: number | null;
    student: Student;
    enrollment: RegisterPaymentRecordApi["enrollment"];
};
type RegisterFilters = {
    search: string;
    date: string;
};
const field =
    "mt-1.5 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/10";
const primary =
    "inline-flex items-center justify-center gap-2 rounded-xl bg-[#17375C] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#254878] disabled:cursor-not-allowed disabled:opacity-50";
const money = (amount: string | number) =>
    `৳ ${new Intl.NumberFormat("en-BD", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(amount) || 0)}`;
const dateLabel = (date?: string | null) =>
    date
        ? new Intl.DateTimeFormat("en-GB", {
              day: "2-digit",
              month: "short",
              year: "numeric",
          }).format(new Date(`${date.slice(0, 10)}T00:00:00`))
        : "—";
const dateTimeLabel = (date: string) =>
    new Intl.DateTimeFormat("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(date));
const localDate = () => {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
};
const makeIdempotencyKey = () =>
    globalThis.crypto?.randomUUID?.() ??
    `${Date.now()}-${Math.random().toString(36).slice(2)}`;
const getRequestErrorMessage = (reason: unknown, fallback: string) => {
    if (reason instanceof ApiRequestError) {
        const firstValidationMessage = Object.values(
            reason.errors ?? {},
        ).flat()[0];
        return firstValidationMessage ?? reason.message;
    }
    return reason instanceof Error ? reason.message : fallback;
};
const escapeHtml = (value: string) =>
    value.replace(
        /[&<>"']/g,
        (character) =>
            ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#039;",
            })[character] ?? character,
    );

/** Use the latest confirmed ledger entry as the receipt shortcut for a charge row. */
/** Student billing workspace connected to the branch-scoped student payment API. */
export default function StudentBillWorkspace() {
    const { auth, branding } = usePage<{
        auth: Auth;
        branding?: {
            logo_url: string;
            name: string;
            phone?: string | null;
            email?: string | null;
        };
    }>().props;
    const { request } = useApi();
    const [student, setStudent] = useState<Student | null>(null);
    const [enrollments, setEnrollments] = useState<BillingOptions["enrollments"]>([]);
    const [paymentEnrollmentId, setPaymentEnrollmentId] = useState<number | null>(null);
    const [expenseEnrollmentId, setExpenseEnrollmentId] = useState<number | null>(null);
    const [registerRows, setRegisterRows] = useState<RegisterPaymentRecord[]>(
        [],
    );
    const [registerMeta, setRegisterMeta] = useState({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    });
    const [registerPage, setRegisterPage] = useState(1);
    const [registerFilter, setRegisterFilter] = useState("");
    const [registerDateFilter, setRegisterDateFilter] = useState("");
    const [appliedRegisterFilters, setAppliedRegisterFilters] =
        useState<RegisterFilters>({
            search: "",
            date: "",
        });
    const [registerRefreshKey, setRegisterRefreshKey] = useState(0);
    const [loadingRegister, setLoadingRegister] = useState(false);
    const [paymentOptions, setPaymentOptions] = useState<PaymentOptions>({
        payment_methods: [],
    });
    const [expenseOptions, setExpenseOptions] = useState<ExpenseOption[]>([]);
    const [loadingEnrollment, setLoadingEnrollment] = useState(false);
    const [saving, setSaving] = useState(false);
    const [bkashSelected, setBkashSelected] = useState(true);
    const [paymentStudentId, setPaymentStudentId] = useState("");
    const [expenseStudentId, setExpenseStudentId] = useState("");
    const [dialog, setDialog] = useState<
        "payment" | "expense" | "view" | null
    >(null);
    const [selectedPayment, setSelectedPayment] =
        useState<RegisterPaymentRecord | null>(null);
    const [notice, setNotice] = useState("");
    const [error, setError] = useState("");
    const [paymentForm, setPaymentForm] = useState({
        amount: "",
        paid_on: localDate(),
        method_id: "",
        reference: "",
        payer_phone: "",
        notes: "",
    });
    const [paymentIdempotencyKey, setPaymentIdempotencyKey] = useState("");
    const [expenseForm, setExpenseForm] = useState({
        type_id: "",
        issued_on: localDate(),
        notes: "",
        payment_amount: "",
        payment_method_id: "",
        payment_reference: "",
        payer_phone: "",
    });
    const selectedEnrollment = enrollments.find((item) => item.id === paymentEnrollmentId) ?? null;
    const selectedExpenseEnrollment = enrollments.find((item) => item.id === expenseEnrollmentId) ?? null;
    const visibleRegisterRows = registerRows;
    const paymentMethodChoices = paymentOptions.payment_methods;
    const expensePaymentMethods = selectedExpenseEnrollment?.payment_methods ?? [];
    const selectedPaymentMethod = paymentMethodChoices.find(
        (method) => String(method.id) === paymentForm.method_id,
    );
    const selectedExpensePaymentMethod = expensePaymentMethods.find(
        (method) => String(method.id) === expenseForm.payment_method_id,
    );
    // The current mobile banking choice represents bKash and stays unverified in the UI.
    const isBkashPayment = selectedExpensePaymentMethod?.code.toLowerCase().endsWith("mobile_banking") === true;
    const isTuitionBkashPayment = selectedPaymentMethod?.code.toLowerCase().endsWith("mobile_banking") === true;
    const issuerName = auth.user.full_name || auth.user.name;

    /** Load individual payment records; the service applies branch scope before pagination. */
    const loadRegister = useCallback(
        async (signal: AbortSignal, page: number, filters: RegisterFilters) => {
            setLoadingRegister(true);
            try {
                const query = new URLSearchParams({
                    page: String(page),
                    per_page: String(registerMeta.per_page),
                });
                if (filters.search) query.set("search", filters.search);
                if (filters.date) query.set("date", filters.date);
                const response = await request<{
                    data: RegisterPaymentRecordApi[];
                    meta: typeof registerMeta;
                }>(`/student-payments?${query.toString()}`, { signal });
                if (signal.aborted) return;
                setRegisterRows(
                    response.data.data.map((row) => ({
                        id: row.id,
                        payment_key: row.payment_key,
                        payment_number: row.payment_number,
                        amount: row.amount,
                        paid_at: row.paid_at,
                        status: row.status,
                        payment_method: row.payment_method,
                        transaction_reference: row.transaction_reference,
                        payer_phone: row.payer_phone,
                        notes: row.notes,
                        recorded_by_name: row.recorded_by_name,
                        source: row.source,
                        category: row.category,
                        description: row.description,
                        expense_id: row.expense_id,
                        student: row.student,
                        enrollment: row.enrollment,
                    })),
                );
                setRegisterMeta(response.data.meta);
            } catch (reason) {
                if (!signal.aborted) {
                    setRegisterRows([]);
                    setError(
                        getRequestErrorMessage(
                            reason,
                            "Unable to load student payment records.",
                        ),
                    );
                }
            } finally {
                if (!signal.aborted) setLoadingRegister(false);
            }
        },
        [registerMeta.per_page, request],
    );

    useEffect(() => {
        const controller = new AbortController();
        void loadRegister(
            controller.signal,
            registerPage,
            appliedRegisterFilters,
        );
        return () => controller.abort();
    }, [
        appliedRegisterFilters,
        loadRegister,
        registerPage,
        registerRefreshKey,
    ]);

    /** Fetch branch-scoped billing choices from the exact Student ID. */
    async function lookupBillingOptions(studentCode: string): Promise<BillingOptions | null> {
        if (!studentCode.trim()) {
            setError("Enter a Student ID first.");
            return null;
        }
        setLoadingEnrollment(true);
        const controller = new AbortController();
        setError("");
        try {
            const response = await request<Envelope<BillingOptions>>(
                `/students/search?student_code=${encodeURIComponent(studentCode.trim())}`,
                { signal: controller.signal },
            );
            const options = response.data.data;
            setStudent(options.student);
            setEnrollments(options.enrollments);
            const selectedPaymentId = paymentEnrollmentId;
            const selectedExpenseId = expenseEnrollmentId;
            setPaymentEnrollmentId(options.enrollments.some((item) => item.id === selectedPaymentId) ? selectedPaymentId : null);
            setExpenseEnrollmentId(options.enrollments.some((item) => item.id === selectedExpenseId) ? selectedExpenseId : null);
            setPaymentOptions({ payment_methods: options.enrollments.find((item) => item.id === selectedPaymentId)?.payment_methods ?? [] });
            setExpenseOptions(options.enrollments.find((item) => item.id === selectedExpenseId)?.expense_items ?? []);
            return options;
        } catch (reason) {
            setStudent(null);
            setEnrollments([]);
            setPaymentEnrollmentId(null);
            setExpenseEnrollmentId(null);
            setPaymentOptions({ payment_methods: [] });
            setExpenseOptions([]);
            setError(getRequestErrorMessage(reason, "Unable to find this student in your billing branch."));
            return null;
        } finally {
            setLoadingEnrollment(false);
        }
    }

    function choosePaymentEnrollment(id: number) {
        const selected = enrollments.find((item) => item.id === id);
        if (!selected) return;
        setPaymentEnrollmentId(id);
        setPaymentOptions({ payment_methods: selected.payment_methods });
        setPaymentForm((current) => ({ ...current, amount: "" }));
    }

    function chooseExpenseEnrollment(id: number) {
        const selected = enrollments.find((item) => item.id === id);
        if (!selected) return;
        setExpenseEnrollmentId(id);
        setPaymentOptions({ payment_methods: selected.payment_methods });
        setExpenseOptions(selected.expense_items);
        setExpenseForm((current) => ({ ...current, type_id: "", payment_amount: "" }));
    }

    /** Open payment details directly from a saved receipt row. */
    function openRegisterCharge(row: RegisterPaymentRecord) {
        setStudent(row.student);
        setEnrollments([]);
        setExpenseOptions([]);
        setPaymentEnrollmentId(null);
        setExpenseEnrollmentId(null);
        setPaymentOptions({ payment_methods: [] });
        setSelectedPayment(row);
        setDialog("view");
    }

    async function openExpenseDialog() {
        setDialog("expense");
        setExpenseStudentId(student?.student_code ?? "");
        setExpenseEnrollmentId(null);
        setExpenseOptions([]);
        setPaymentOptions({ payment_methods: [] });
        setExpenseForm({
            type_id: "",
            issued_on: localDate(),
            notes: "",
            payment_amount: "",
            payment_method_id: "",
            payment_reference: "",
            payer_phone: "",
        });
        setBkashSelected(true);
        setPaymentIdempotencyKey(makeIdempotencyKey());
        setError("");
    }

    function openPayment() {
        setSelectedPayment(null);
        setPaymentEnrollmentId(null);
        setPaymentOptions({ payment_methods: [] });
        setPaymentIdempotencyKey(makeIdempotencyKey());
        setPaymentStudentId(student?.student_code ?? "");
        setPaymentForm({
            amount: "",
            paid_on: localDate(),
            method_id: "",
            reference: "",
            payer_phone: "",
            notes: "",
        });
        setError("");
        setDialog("payment");
    }

    async function submitPayment(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!student || !paymentEnrollmentId) {
            setError("Find the student and choose an enrollment before submitting.");
            return;
        }
        setSaving(true);
        setError("");
        try {
            const payload = {
                student_id: paymentStudentId.trim(),
                enrollment_id: paymentEnrollmentId,
                amount: paymentForm.amount,
                payment_method_id: Number(paymentForm.method_id),
                idempotency_key: paymentIdempotencyKey,
                paid_at: paymentForm.paid_on,
                transaction_reference: paymentForm.reference || null,
                payer_phone: paymentForm.payer_phone || null,
                notes: paymentForm.notes || null,
            };
            const response = await request<Envelope<{ idempotent_replay?: boolean }>>(
                "/student-payments/tuition",
                { method: "POST", data: payload },
            );
            setDialog(null);
            setNotice(
                response.status === 200 || response.data.data.idempotent_replay
                    ? "This payment was already recorded. The existing payment record is available in the register."
                    : "Payment recorded successfully.",
            );
            setRegisterRefreshKey((current) => current + 1);
            void lookupBillingOptions(paymentStudentId);
        } catch (reason) {
            setError(
                getRequestErrorMessage(
                    reason,
                    "Unable to record this payment.",
                ),
            );
        } finally {
            setSaving(false);
        }
    }

    async function submitExpense(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!student || !expenseEnrollmentId) {
            setError("Find the student and choose an enrollment before submitting.");
            return;
        }
        setSaving(true);
        setError("");
        try {
            const data = {
                student_id: expenseStudentId.trim(),
                enrollment_id: expenseEnrollmentId,
                student_expense_type_id: Number(expenseForm.type_id),
                amount: expenseForm.payment_amount,
                payment_method_id: Number(expenseForm.payment_method_id),
                idempotency_key: paymentIdempotencyKey,
                paid_at: expenseForm.issued_on,
                transaction_reference: expenseForm.payment_reference || null,
                payer_phone: expenseForm.payer_phone || null,
                notes: expenseForm.notes || null,
            };
            const response = await request<Envelope<{ idempotent_replay?: boolean }>>(
                "/student-payments/other-expenses", { method: "POST", data },
            );
            setDialog(null);
            setNotice(
                response.data.data.idempotent_replay
                    ? "This bill and payment were already recorded."
                    : "Student bill and payment recorded successfully.",
            );
            setRegisterRefreshKey((current) => current + 1);
            void lookupBillingOptions(expenseStudentId);
        } catch (reason) {
            setError(
                getRequestErrorMessage(
                    reason,
                    "Unable to issue this student expense.",
                ),
            );
        } finally {
            setSaving(false);
        }
    }

    function printReceipt(
        payment: RegisterPaymentRecord,
        registerContext?: {
            student: Student;
            courseName: string | null;
            batchName: string | null;
            branchName: string | null;
            branchPhone: string | null;
            branchEmail: string | null;
            branchAddress: string | null;
        },
    ) {
        // The receipt uses the recorded issuer and server-issued receipt number from the API.
        const printWindow = window.open("", "_blank", "width=820,height=900");
        if (!printWindow) {
            setNotice(
                "Allow pop-ups for this site to print the payment receipt.",
            );
            return;
        }
        const receiptMetadata = [
            ["Receipt number", payment.payment_number],
            ["Payment date", dateTimeLabel(payment.paid_at)],
            ["Payment method", payment.payment_method ?? "Cash"],
            ["Verification status", payment.status],
            ["Issued by", payment.recorded_by_name ?? issuerName],
            ...(payment.transaction_reference
                ? [["Transaction reference", payment.transaction_reference]]
                : []),
        ]
            .map(
                ([label, value]) =>
                    `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></div>`,
            )
            .join("");
        const logoUrl = escapeHtml(
            branding?.logo_url ||
                `${window.location.origin}/apple-touch-icon.png`,
        );
        const issuer = payment.recorded_by_name ?? issuerName;
        const receiptStudent = registerContext?.student ?? student;
        const receiptCourseName =
            registerContext?.courseName ?? selectedEnrollment?.course?.name;
        const receiptBatchName =
            registerContext?.batchName ?? selectedEnrollment?.batch?.name;
        const receiptBranchOffice = registerContext?.branchName
            ? `CMUD ${registerContext.branchName} Branch Office`
            : `CMUD ${auth.user.branch?.name ?? "Branch"} Office`;
        const branchContact = [registerContext?.branchAddress, registerContext?.branchPhone, registerContext?.branchEmail]
            .filter(Boolean)
            .map((value) => escapeHtml(value));

        printWindow.document.open();
        printWindow.document
            .write(`<!doctype html><html><head><meta charset="utf-8"><title>Payment receipt ${escapeHtml(payment.payment_number)}</title><style>
            *{box-sizing:border-box}body{margin:0;color:#111827;font:12px Arial,sans-serif}.receipt{width:100%;padding:32px 40px}.header{display:flex;justify-content:space-between;gap:24px;border-bottom:2px solid #17375c;padding-bottom:18px}.brand img{display:block;width:auto;height:48px;max-width:190px;object-fit:contain;object-position:left}.brand p{margin:5px 0 0;color:#64748b;font-size:11px}.header-title{text-align:right}.header-title h1{margin:0;color:#17375c;font-size:22px}.header-title p{margin:5px 0 0;color:#64748b;font-size:10px;font-weight:bold;letter-spacing:.16em;text-transform:uppercase}.content{display:grid;grid-template-columns:1fr 1fr;gap:30px;margin-top:24px}.label{margin:0 0 8px;color:#64748b;font-size:10px;font-weight:bold;letter-spacing:.14em;text-transform:uppercase}.student-name{margin:0;font-size:15px;font-weight:bold}.student-extra{margin:5px 0 0;color:#475569;font-size:11px}.meta{display:grid;grid-template-columns:1fr 1fr;gap:10px 16px;margin:0;padding:13px;border:1px solid #e2e8f0;border-radius:6px}.meta dt{color:#64748b;font-size:9px}.meta dd{margin:3px 0 0;font-size:11px;font-weight:bold;overflow-wrap:anywhere}.items{margin-top:26px}.items-heading{display:flex;justify-content:space-between;align-items:end;margin-bottom:8px}.items-heading h2{margin:0;font-size:14px}.items-heading span{color:#64748b;font-size:10px}table{width:100%;border-collapse:collapse;font-size:11px}th{border-top:1px solid #cbd5e1;border-bottom:1px solid #cbd5e1;background:#f8fafc;padding:9px 10px;text-align:left}td{border-bottom:1px solid #e2e8f0;padding:12px 10px}th:last-child,td:last-child{text-align:right}.signatures{display:flex;justify-content:space-between;gap:48px;margin-top:58px}.signature-block{width:220px;text-align:center}.signature-name{min-height:16px;font-size:11px;font-weight:bold}.signature-line{margin-top:28px;border-top:1px solid #94a3b8;padding-top:7px;color:#475569;font-size:10px}.footer{margin-top:28px;border-top:1px solid #e2e8f0;padding-top:9px;color:#64748b;font-size:9px;text-align:center}@page{size:A4;margin:14mm}@media print{.receipt{padding:0}.header,.content,.items,table,.signatures,.footer{break-inside:avoid}}
            </style></head><body><main class="receipt"><header class="header"><div class="brand"><img src="${logoUrl}" alt="${escapeHtml(branding?.name ?? "CMUD")} logo"><p>${escapeHtml(receiptBranchOffice)}</p>${branchContact.map((value) => `<p>${value}</p>`).join("")}</div><div class="header-title"><h1>PAYMENT RECEIPT</h1><p>Student payment record</p></div></header><section class="content"><div><h2 class="label">Student</h2><p class="student-name">${escapeHtml(receiptStudent?.full_name ?? "Student")}</p><p class="student-extra">Student ID: ${escapeHtml(receiptStudent?.student_code ?? "")}</p><p class="student-extra">Course: ${escapeHtml(receiptCourseName ?? "—")}</p><p class="student-extra">Batch: ${escapeHtml(receiptBatchName ?? "—")}</p></div><dl class="meta">${receiptMetadata}</dl></section><section class="items"><div class="items-heading"><h2>${escapeHtml(payment.description)}</h2><span>Currency: BDT</span></div><table><thead><tr><th>#</th><th>Payment detail</th><th>Amount received</th></tr></thead><tbody><tr><td>1</td><td>${escapeHtml(payment.category)} payment</td><td>${escapeHtml(money(payment.amount))}</td></tr></tbody></table></section><section class="signatures"><div class="signature-block"><div class="signature-name">${escapeHtml(issuer)}</div><div class="signature-line">Issued by / Signature</div></div></section></main><script>window.addEventListener('load',function(){window.focus();window.print();});window.addEventListener('afterprint',function(){window.close();});</script></body></html>`);
        printWindow.document.close();
    }

    const pageTitle = "Student payment";

    return (
        <AppLayout
            breadcrumbs={[
                { title: "Billings", href: "/billings/student-bill" },
                { title: pageTitle, href: "/billings/student-bill" },
            ]}
        >
            <Head title={pageTitle} />
            <main className="min-h-screen bg-[#F7F9FC] px-4 py-6 text-slate-800 sm:px-7 lg:px-10">
                <div className="mx-auto max-w-7xl space-y-6">
                    <header className="flex flex-wrap items-center justify-between gap-5 rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-6 text-white sm:p-8">
                        <div>
                            <p className="flex items-center gap-2 text-xs font-semibold tracking-[0.16em] text-cyan-100 uppercase">
                                <ReceiptText size={16} /> Billings
                            </p>
                            <h1 className="mt-3 text-3xl font-semibold tracking-tight">
                                {pageTitle}
                            </h1>
                            <p className="mt-2 text-sm text-cyan-50/85">
                                Find student payment records and record
                                receipts.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => openPayment()}
                                className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-[#17375C] hover:bg-cyan-50 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <CreditCard size={17} /> Create tuition payment
                            </button>
                            <button
                                type="button"
                                onClick={() => void openExpenseDialog()}
                                className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-[#17375C] hover:bg-cyan-50 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <Plus size={17} /> Create student other bill
                            </button>
                        </div>
                    </header>
                    {notice && (
                        <p
                            role="status"
                            className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                        >
                            {notice}
                        </p>
                    )}
                    {error && !dialog && (
                        <p
                            role="alert"
                            className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
                        >
                            {error}
                        </p>
                    )}
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="font-semibold text-[#17375C]">
                                    Find billing records
                                </h2>
                                <p className="mt-1 text-xs text-slate-500">
                                    Search billing records by student and payment date.
                                </p>
                            </div>
                        </div>
                        <div className="grid items-end gap-4 sm:grid-cols-[minmax(0,1fr)_220px_auto]">
                            <label className="block text-xs font-medium text-slate-600">
                                Student ID or name
                                <span className="relative mt-1.5 block">
                                    <Search
                                        size={16}
                                        className="absolute top-1/2 left-3 -translate-y-1/2 text-slate-400"
                                    />
                                    <input
                                        value={registerFilter}
                                        onChange={(event) => {
                                            setRegisterFilter(
                                                event.target.value,
                                            );
                                        }}
                                        placeholder="Search student by ID or name"
                                        className={`${field} mt-0 pl-9`}
                                    />
                                </span>
                            </label>
                            <label className="block text-xs font-medium text-slate-600">
                                Payment date
                                <input
                                    type="date"
                                    value={registerDateFilter}
                                    onChange={(event) => setRegisterDateFilter(event.target.value)}
                                    className={field}
                                />
                            </label>
                            <button
                                type="button"
                                onClick={() => {
                                    setRegisterPage(1);
                                    setAppliedRegisterFilters({
                                        search: registerFilter.trim(),
                                        date: registerDateFilter,
                                    });
                                }}
                                className={primary}
                            >
                                <Search size={16} /> Search
                            </button>
                        </div>
                    </section>
                    <section
                        className="overflow-hidden rounded-2xl border border-slate-200 bg-white"
                        aria-labelledby="student-payment-register"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-5">
                            <div>
                                <h2
                                    id="student-payment-register"
                                    className="font-semibold text-[#17375C]"
                                >
                                    Student payment records
                                    <span className="ml-2 text-sm font-normal text-slate-400">
                                        {registerMeta.total} records
                                    </span>
                                </h2>
                                <p className="mt-1 text-xs text-slate-500">
                                    Individual tuition and expense payments in
                                    your authorized branch scope.
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                {loadingRegister && (
                                    <LoaderCircle
                                        size={18}
                                        className="animate-spin text-slate-400"
                                    />
                                )}
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-slate-50 text-xs text-slate-500">
                                    <tr>
                                        {[
                                            "Receipt",
                                            "Student",
                                            "Payment date",
                                            "Amount",
                                            "Method",
                                            "Status",
                                            "Actions",
                                        ].map((label) => (
                                            <th
                                                key={label}
                                                className="px-5 py-3 font-medium whitespace-nowrap"
                                            >
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {visibleRegisterRows.map((record) => (
                                        <tr key={record.payment_key}>
                                            <td className="px-5 py-4 font-mono text-xs whitespace-nowrap text-[#17375C]">
                                                {record.payment_number}
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-medium whitespace-nowrap text-[#17375C]">
                                                    {record.student.full_name}
                                                </p>
                                                <p className="mt-1 text-xs whitespace-nowrap text-slate-500">
                                                    {
                                                        record.student
                                                            .student_code
                                                    }
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-xs whitespace-nowrap text-slate-500">
                                                {dateTimeLabel(record.paid_at)}
                                            </td>
                                            <td className="px-5 py-4 text-xs font-semibold whitespace-nowrap">
                                                {money(record.amount)}
                                            </td>
                                            <td className="px-5 py-4 text-xs whitespace-nowrap">
                                                {record.payment_method ??
                                                    "Cash"}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span
                                                    className={`rounded-full px-2.5 py-1 text-xs font-medium ${record.status === "confirmed" ? "bg-emerald-50 text-emerald-700" : record.status === "pending" ? "bg-amber-50 text-amber-700" : "bg-rose-50 text-rose-700"}`}
                                                >
                                                    {record.status.replace(
                                                        /^./,
                                                        (character) =>
                                                            character.toUpperCase(),
                                                    )}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="flex gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => openRegisterCharge(record)}
                                                        aria-label={`View payment ${record.payment_number}`}
                                                        title="View payment"
                                                        className="rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-50"
                                                    >
                                                        <Eye size={16} />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            printReceipt(
                                                                record,
                                                                {
                                                                    student: record.student,
                                                                    courseName: record.enrollment.course_name,
                                                                    batchName: record.enrollment.batch_name,
                                                                    branchName: record.enrollment.branch_name,
                                                                    branchPhone: record.enrollment.branch_phone,
                                                                    branchEmail: record.enrollment.branch_email,
                                                                    branchAddress: record.enrollment.branch_address,
                                                                },
                                                            )
                                                        }
                                                        aria-label={`Print payment receipt ${record.payment_number}`}
                                                        title="Print payment receipt"
                                                        className="rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-50"
                                                    >
                                                        <Printer size={16} />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {!visibleRegisterRows.length && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="px-6 py-12 text-center text-slate-500"
                                            >
                                                {loadingRegister
                                                    ? "Loading student payment records?"
                                                    : "No payment records were found in your branch scope."}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="flex items-center justify-between border-t border-slate-100 px-6 py-4 text-xs text-slate-500">
                            <span>
                                Page {registerMeta.current_page} of{" "}
                                {registerMeta.last_page}
                            </span>
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    disabled={
                                        loadingRegister || registerPage <= 1
                                    }
                                    onClick={() =>
                                        setRegisterPage((page) =>
                                            Math.max(1, page - 1),
                                        )
                                    }
                                    className="rounded-lg border border-slate-200 px-3 py-2 disabled:opacity-40"
                                >
                                    Previous
                                </button>
                                <button
                                    type="button"
                                    disabled={
                                        loadingRegister ||
                                        registerPage >= registerMeta.last_page
                                    }
                                    onClick={() =>
                                        setRegisterPage((page) =>
                                            Math.min(
                                                registerMeta.last_page,
                                                page + 1,
                                            ),
                                        )
                                    }
                                    className="rounded-lg border border-slate-200 px-3 py-2 disabled:opacity-40"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </main>

            <Dialog
                open={dialog !== null}
                onOpenChange={(open) => {
                    if (!open && !saving) setDialog(null);
                }}
            >
                <DialogContent
                    className={`max-h-[85svh] overflow-y-auto ${dialog === "view" ? "sm:max-w-2xl" : "sm:max-w-xl"}`}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {dialog === "payment"
                                ? "Record student payment"
                                : dialog === "expense"
                                  ? "Create bill & payment"
                                  : selectedPayment
                                    ? "Payment record details"
                                    : "Charge details"}
                        </DialogTitle>
                        <DialogDescription>
                            {dialog === "payment"
                                ? "Record a tuition payment for a student in your billing branch."
                                : dialog === "expense"
                                  ? "Create an other student bill and record its payment together."
                                  : selectedPayment
                                    ? "Review this payment record and its linked charge."
                                    : "Review the selected student charge."}
                        </DialogDescription>
                    </DialogHeader>
                    {dialog === "payment" && (
                        <form onSubmit={submitPayment} className="space-y-4">
                            <label className="block text-xs font-medium text-slate-600">
                                Student ID
                                <input
                                    required
                                    value={paymentStudentId}
                                    onChange={(event) => setPaymentStudentId(event.target.value)}
                                    placeholder="Enter student ID"
                                    className={field}
                                />
                                <button type="button" disabled={loadingEnrollment || !paymentStudentId.trim()} onClick={() => void lookupBillingOptions(paymentStudentId)} className="mt-2 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 disabled:opacity-50">
                                    {loadingEnrollment ? "Checking student…" : "Find student"}
                                </button>
                            </label>
                            {student && <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{student.full_name} · {student.student_code}</p>}
                            {enrollments.length > 0 && (
                                <label className="block text-xs font-medium text-slate-600">Enrollment
                                    <select required value={paymentEnrollmentId ?? ""} onChange={(event) => choosePaymentEnrollment(Number(event.target.value))} className={field}>
                                        <option value="">Choose an enrollment</option>
                                        {enrollments.map((item) => <option key={item.id} value={item.id}>{item.course?.name ?? item.enrollment_code} · {item.branch?.name} · {item.enrollment_code}</option>)}
                                    </select>
                                </label>
                            )}
                            {student && enrollments.length === 0 && <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">No running enrollments are available for this student in your branch.</p>}
                            <div className="grid gap-4 sm:grid-cols-2">
                                {!isTuitionBkashPayment && (
                                    <label className="block text-xs font-medium text-slate-600">
                                        Payment amount (BDT)
                                        <input
                                            required
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            value={paymentForm.amount}
                                            onChange={(event) => setPaymentForm({ ...paymentForm, amount: event.target.value })}
                                            className={field}
                                        />
                                    </label>
                                )}
                                <label className="block text-xs font-medium text-slate-600">
                                    Payment date
                                    <input
                                        required
                                        type="date"
                                        max={localDate()}
                                        value={paymentForm.paid_on}
                                        onChange={(event) =>
                                            setPaymentForm({
                                                ...paymentForm,
                                                paid_on: event.target.value,
                                            })
                                        }
                                        className={field}
                                    />
                                </label>
                            </div>
                            <label className="block text-xs font-medium text-slate-600">
                                Payment method
                                <select
                                    required
                                    value={paymentForm.method_id}
                                    onChange={(event) => {
                                        setPaymentForm({
                                            ...paymentForm,
                                            method_id: event.target.value,
                                            reference: "",
                                            payer_phone: "",
                                        });
                                        setBkashSelected(true);
                                    }}
                                    className={field}
                                >
                                    <option value="">
                                        Select a payment method
                                    </option>
                                    {paymentMethodChoices.map(
                                        (method) => (
                                            <option
                                                key={method.id}
                                                value={method.id}
                                            >
                                                {method.name}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </label>
                            {isTuitionBkashPayment && (
                                <div className="space-y-3 rounded-xl border border-pink-200 bg-pink-50/70 p-4">
                                    <div className="flex items-center justify-between gap-3">
                                        <label className="flex items-center gap-2 text-sm font-medium text-slate-700">
                                            <input
                                                type="checkbox"
                                                checked={bkashSelected}
                                                onChange={(event) => setBkashSelected(event.target.checked)}
                                                className="rounded border-slate-300 text-pink-600"
                                            />
                                            bKash
                                        </label>
                                        <div className="flex items-center gap-2">
                                            <span className="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-800">
                                                Unverified
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => setNotice("Verification API integration will be added later.")}
                                                className="rounded-md border border-pink-200 bg-white px-2 py-1 text-[11px] font-semibold text-pink-700 hover:bg-pink-100"
                                            >
                                                Request verification
                                            </button>
                                        </div>
                                    </div>
                                    <label className="block text-xs font-medium text-slate-600">
                                        Payment amount (BDT)
                                        <input
                                            required
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            value={paymentForm.amount}
                                            onChange={(event) => setPaymentForm({ ...paymentForm, amount: event.target.value })}
                                            className={field}
                                        />
                                    </label>
                                    <label className="block text-xs font-medium text-slate-600">
                                        Transaction ID
                                        <input
                                            required
                                            value={paymentForm.reference}
                                            onChange={(event) => setPaymentForm({ ...paymentForm, reference: event.target.value })}
                                            className={field}
                                        />
                                    </label>
                                    <label className="block text-xs font-medium text-slate-600">
                                        Payer phone number
                                        <input
                                            required
                                            type="tel"
                                            value={paymentForm.payer_phone}
                                            onChange={(event) => setPaymentForm({ ...paymentForm, payer_phone: event.target.value })}
                                            className={field}
                                        />
                                    </label>
                                </div>
                            )}
                            {!isTuitionBkashPayment && (
                                <label className="block text-xs font-medium text-slate-600">
                                    Transaction / reference
                                    <input
                                        value={paymentForm.reference}
                                        onChange={(event) => setPaymentForm({ ...paymentForm, reference: event.target.value })}
                                        className={field}
                                    />
                                </label>
                            )}
                            {!isTuitionBkashPayment && selectedPaymentMethod?.requires_sender_phone && (
                                <label className="block text-xs font-medium text-slate-600">
                                Payer phone number
                                    <input
                                        required
                                        type="tel"
                                        value={paymentForm.payer_phone}
                                        onChange={(event) =>
                                            setPaymentForm({
                                                ...paymentForm,
                                                payer_phone: event.target.value,
                                            })
                                        }
                                        className={field}
                                    />
                                </label>
                            )}
                            <label className="block text-xs font-medium text-slate-600">
                                Remarks (optional)
                                <textarea
                                    value={paymentForm.notes}
                                    onChange={(event) =>
                                        setPaymentForm({
                                            ...paymentForm,
                                            notes: event.target.value,
                                        })
                                    }
                                    rows={2}
                                    className={`${field} h-auto py-3`}
                                />
                            </label>
                            {!paymentOptions.payment_methods.length && (
                                <p className="text-sm text-amber-700">
                                    No active payment method is configured for
                                    this institute.
                                </p>
                            )}
                            {error && (
                                <p
                                    role="alert"
                                    className="text-sm text-rose-700"
                                >
                                    {error}
                                </p>
                            )}
                            <div className="flex justify-end gap-3 border-t border-slate-100 pt-4">
                                <button
                                    type="button"
                                    disabled={saving}
                                    onClick={() => setDialog(null)}
                                    className="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-600"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={
                                        saving ||
                                        !student || !paymentEnrollmentId || !paymentOptions.payment_methods.length || !paymentForm.amount || !paymentForm.method_id ||
                                        (isTuitionBkashPayment && !bkashSelected)
                                    }
                                    className={primary}
                                >
                                    {saving ? (
                                        <LoaderCircle
                                            size={16}
                                            className="animate-spin"
                                        />
                                    ) : (
                                        <CreditCard size={16} />
                                    )}
                                    {saving ? "Recording…" : "Record payment"}
                                </button>
                            </div>
                        </form>
                    )}
                    {dialog === "expense" && (
                        <form onSubmit={submitExpense} className="space-y-4">
                            <label className="block text-xs font-medium text-slate-600">
                                Student ID
                                <input
                                    required
                                    value={expenseStudentId}
                                    onChange={(event) => setExpenseStudentId(event.target.value)}
                                    placeholder="Enter student ID"
                                    className={field}
                                />
                                <button type="button" disabled={loadingEnrollment || !expenseStudentId.trim()} onClick={() => void lookupBillingOptions(expenseStudentId)} className="mt-2 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 disabled:opacity-50">
                                    {loadingEnrollment ? "Checking student…" : "Find student"}
                                </button>
                            </label>
                            {student && <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{student.full_name} · {student.student_code}</p>}
                            {enrollments.length > 0 && (
                                <label className="block text-xs font-medium text-slate-600">Enrollment
                                    <select required value={expenseEnrollmentId ?? ""} onChange={(event) => chooseExpenseEnrollment(Number(event.target.value))} className={field}>
                                        <option value="">Choose an enrollment</option>
                                        {enrollments.map((item) => <option key={item.id} value={item.id}>{item.course?.name ?? item.enrollment_code} · {item.branch?.name}</option>)}
                                    </select>
                                </label>
                            )}
                            {student && enrollments.length === 0 && <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">No running enrollments are available for this student in your branch.</p>}
                            <label className="block text-xs font-medium text-slate-600">
                                Other expense item
                                <select
                                    required
                                    value={expenseForm.type_id}
                                    onChange={(event) => {
                                        const typeId = event.target.value;
                                        const option = expenseOptions.find((item) => String(item.id) === typeId);
                                        const price = option?.price;
                                        setExpenseForm({
                                            ...expenseForm,
                                            type_id: typeId,
                                            payment_amount: price ? Number(price.amount).toFixed(2) : "",
                                        });
                                    }}
                                    className={field}
                                >
                                    <option value="">Choose an other expense item</option>
                                    {expenseOptions.map((option) => (
                                        <option key={option.id} value={option.id}>{option.name} · {money(option.price.amount)}</option>
                                    ))}
                                </select>
                            </label>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {!isBkashPayment && (
                                    <label className="block text-xs font-medium text-slate-600">
                                    Payment amount (BDT)
                                    <input
                                        required
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={expenseForm.payment_amount}
                                        onChange={(event) => setExpenseForm({ ...expenseForm, payment_amount: event.target.value })}
                                        className={field}
                                    />
                                    </label>
                                )}
                                <label className="block text-xs font-medium text-slate-600">
                                    Payment date
                                    <input
                                        required
                                        type="date"
                                        max={localDate()}
                                        value={expenseForm.issued_on}
                                        onChange={(event) => setExpenseForm({ ...expenseForm, issued_on: event.target.value })}
                                        className={field}
                                    />
                                </label>
                            </div>
                            <label className="block text-xs font-medium text-slate-600">
                                Payment method
                                <select
                                    required
                                    value={expenseForm.payment_method_id}
                                    onChange={(event) => {
                                        setExpenseForm({
                                            ...expenseForm,
                                            payment_method_id: event.target.value,
                                            payment_reference: "",
                                            payer_phone: "",
                                        });
                                        setBkashSelected(true);
                                    }}
                                    className={field}
                                >
                                    <option value="">Select a payment method</option>
                                    {expensePaymentMethods.map((method) => (
                                        <option key={method.id} value={method.id}>{method.name}</option>
                                    ))}
                                </select>
                            </label>
                            {isBkashPayment && (
                                <div className="space-y-3 rounded-xl border border-pink-200 bg-pink-50/70 p-4">
                                    <div className="flex items-center justify-between gap-3">
                                        <label className="flex items-center gap-2 text-sm font-medium text-slate-700">
                                            <input
                                                type="checkbox"
                                                checked={bkashSelected}
                                                onChange={(event) => setBkashSelected(event.target.checked)}
                                                className="rounded border-slate-300 text-pink-600"
                                            />
                                            bKash
                                        </label>
                                        <div className="flex items-center gap-2">
                                            <span className="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-800">Unverified</span>
                                            <button
                                                type="button"
                                                onClick={() => setNotice("Verification API integration will be added later.")}
                                                className="rounded-md border border-pink-200 bg-white px-2 py-1 text-[11px] font-semibold text-pink-700 hover:bg-pink-100"
                                            >
                                                Request verification
                                            </button>
                                        </div>
                                    </div>
                                    <label className="block text-xs font-medium text-slate-600">
                                        Payment amount (BDT)
                                        <input
                                            required
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            value={expenseForm.payment_amount}
                                            onChange={(event) => setExpenseForm({ ...expenseForm, payment_amount: event.target.value })}
                                            className={field}
                                        />
                                    </label>
                                    <label className="block text-xs font-medium text-slate-600">
                                        Transaction ID
                                        <input
                                            required
                                            value={expenseForm.payment_reference}
                                            onChange={(event) => setExpenseForm({ ...expenseForm, payment_reference: event.target.value })}
                                            className={field}
                                        />
                                    </label>
                                    <label className="block text-xs font-medium text-slate-600">
                                        Payer phone number
                                        <input
                                            required
                                            type="tel"
                                            value={expenseForm.payer_phone}
                                            onChange={(event) => setExpenseForm({ ...expenseForm, payer_phone: event.target.value })}
                                            className={field}
                                        />
                                    </label>
                                </div>
                            )}
                            {!isBkashPayment && (
                                <label className="block text-xs font-medium text-slate-600">
                                    Transaction / reference
                                    <input
                                        value={expenseForm.payment_reference}
                                        onChange={(event) => setExpenseForm({ ...expenseForm, payment_reference: event.target.value })}
                                        className={field}
                                    />
                                </label>
                            )}
                            <label className="block text-xs font-medium text-slate-600">
                                Remarks (optional)
                                <textarea
                                    value={expenseForm.notes}
                                    onChange={(event) =>
                                        setExpenseForm({
                                            ...expenseForm,
                                            notes: event.target.value,
                                        })
                                    }
                                    rows={2}
                                    className={`${field} h-auto py-3`}
                                />
                            </label>
                            {error && (
                                <p
                                    role="alert"
                                    className="text-sm text-rose-700"
                                >
                                    {error}
                                </p>
                            )}
                            <div className="flex justify-end gap-3 border-t border-slate-100 pt-4">
                                <button
                                    type="button"
                                    disabled={saving}
                                    onClick={() => setDialog(null)}
                                    className="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-600"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={
                                        saving ||
                                        !student || !expenseEnrollmentId ||
                                        !expenseForm.type_id ||
                                        !expenseForm.payment_amount ||
                                        !expenseForm.payment_method_id ||
                                        (isBkashPayment && !bkashSelected)
                                    }
                                    className={primary}
                                >
                                    {saving ? (
                                        <LoaderCircle
                                            size={16}
                                            className="animate-spin"
                                        />
                                    ) : (
                                        <Plus size={16} />
                                    )}
                                    {saving ? "Saving…" : "Create bill & payment"}
                                </button>
                            </div>
                        </form>
                    )}
                    {dialog === "view" &&
                        selectedPayment && (
                        <div className="space-y-5">
                            <section className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="text-xs font-medium tracking-wide text-slate-500 uppercase">
                                            Student
                                        </p>
                                        <h3 className="mt-1 text-lg font-semibold text-[#17375C]">
                                            {student?.full_name}
                                        </h3>
                                        <p className="mt-1 text-sm text-slate-500">
                                            {student?.student_code} ·{" "}
                                            {selectedEnrollment?.course?.name}
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700">
                                        {selectedPayment.status}
                                    </span>
                                </div>
                                <div className="mt-4 grid gap-3 border-t border-slate-200 pt-4 sm:grid-cols-2">
                                    <div>
                                        <p className="text-xs text-slate-500">
                                            Charge
                                        </p>
                                        <p className="mt-1 text-sm font-medium">
                                            {selectedPayment.payment_number}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-slate-500">
                                            Issue date
                                        </p>
                                        <p className="mt-1 text-sm font-medium">
                                            {dateTimeLabel(selectedPayment.paid_at)}
                                        </p>
                                    </div>
                                </div>
                            </section>
                            {selectedPayment && (
                                <section className="rounded-2xl border border-slate-200 p-5">
                                    <h3 className="font-semibold text-[#17375C]">
                                        Payment record
                                    </h3>
                                    <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <dt className="text-xs text-slate-500">
                                                Receipt number
                                            </dt>
                                            <dd className="mt-1 font-mono text-sm font-medium">
                                                {selectedPayment.payment_number}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-slate-500">
                                                Payment date
                                            </dt>
                                            <dd className="mt-1 text-sm font-medium">
                                                {dateTimeLabel(
                                                    selectedPayment.paid_at,
                                                )}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-slate-500">
                                                Amount received
                                            </dt>
                                            <dd className="mt-1 text-sm font-semibold">
                                                {money(selectedPayment.amount)}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-slate-500">
                                                Payment method
                                            </dt>
                                            <dd className="mt-1 text-sm font-medium">
                                                {selectedPayment.payment_method ??
                                                    "Cash"}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-slate-500">
                                                Issued by
                                            </dt>
                                            <dd className="mt-1 text-sm font-medium">
                                                {selectedPayment.recorded_by_name ??
                                                    "—"}
                                            </dd>
                                        </div>
                                        {selectedPayment.transaction_reference && (
                                            <div>
                                                <dt className="text-xs text-slate-500">
                                                    Reference
                                                </dt>
                                                <dd className="mt-1 text-sm font-medium">
                                                    {
                                                        selectedPayment.transaction_reference
                                                    }
                                                </dd>
                                            </div>
                                        )}
                                    </dl>
                                </section>
                            )}
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

