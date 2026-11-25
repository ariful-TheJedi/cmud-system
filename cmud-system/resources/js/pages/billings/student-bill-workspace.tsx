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
    useMemo,
    useRef,
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
    financial_account_id?: number | null;
    transaction_reference?: string | null;
    notes?: string | null;
    recorded_by_name?: string | null;
    source?: "tuition" | "expense";
    obligation_number?: string | null;
};
type Expense = {
    id: number;
    expense_number: string;
    student_expense_type: { id: number; code: string; name: string } | null;
    description: string;
    status: string;
    is_cancelled: boolean;
    issued_on: string;
    due_on: string | null;
    quantity: string;
    unit_amount: string;
    amount: string;
    approved_waiver: string;
    payable_amount: string;
    paid_amount: string;
    outstanding_balance: string;
    payments: Payment[];
};
type Tuition = {
    id: number;
    tuition_number: string;
    status: string;
    is_cancelled: boolean;
    issued_on: string;
    due_on: string | null;
    amount: string;
    approved_waiver: string;
    payable_amount: string;
    paid_amount: string;
    outstanding_balance: string;
    payments: Payment[];
};
type CashAccount = {
    id: number;
    name: string;
    code: string;
    branch_id: number | null;
};
type ExpenseOption = {
    id: number;
    code: string;
    name: string;
    description?: string | null;
    prices: {
        id: number;
        amount: string;
        effective_from: string;
        effective_to: string | null;
    }[];
};
type PaymentOptions = {
    cash_accounts: CashAccount[];
    payment_methods: { id: number; name: string }[];
};
type Envelope<T> = { data: T; message?: string };
type Charge = {
    id: number;
    kind: "tuition" | "expense";
    enrollmentId?: number;
    number: string;
    category: string;
    description: string;
    issuedOn: string;
    amount: string;
    paid: string;
    due: string;
    payments: Payment[];
    cancelled: boolean;
};
type RegisterCharge = Charge & {
    payable_amount: string;
    student: Student;
    enrollment: {
        id: number;
        enrollment_code: string;
        course_name: string | null;
        batch_name: string | null;
        branch_name: string | null;
    };
};
type RegisterChargeApi = {
    id: number;
    kind: "tuition" | "expense";
    number: string;
    category: string;
    description: string;
    issued_on: string;
    is_cancelled: boolean;
    amount: string;
    payable_amount: string;
    paid_amount: string;
    outstanding_balance: string;
    payments: Payment[];
    student: Student;
    enrollment: RegisterCharge["enrollment"];
};
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
    recorded_by_name: string | null;
    student: Student;
    enrollment: RegisterCharge["enrollment"];
    charge: {
        id: number;
        kind: "tuition" | "expense";
        number: string;
        category: string;
        description: string;
        issued_on: string;
        amount: string;
        paid: string;
        due: string;
    };
};
type RegisterPaymentRecord = Payment & {
    payment_key: string;
    student: Student;
    enrollment: RegisterCharge["enrollment"];
    charge: Charge & { enrollmentId: number };
};
type RegisterFilters = {
    search: string;
    courseId: string;
    batchId: string;
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

function statusLabel(charge: Charge) {
    if (charge.cancelled) return "Cancelled";
    if (Number(charge.due) <= 0) return "Paid";
    return Number(charge.paid) > 0 ? "Partial" : "Unpaid";
}

/** Use the latest confirmed ledger entry as the receipt shortcut for a charge row. */
/** Student billing workspace connected to the enrollment-scoped Finance API. */
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
    const [enrollments, setEnrollments] = useState<Enrollment[]>([]);
    const [enrollmentId, setEnrollmentId] = useState<number | null>(null);
    const [registerRows, setRegisterRows] = useState<RegisterPaymentRecord[]>(
        [],
    );
    const [chargeRows, setChargeRows] = useState<RegisterCharge[]>([]);
    const [registerMeta, setRegisterMeta] = useState({
        current_page: 1,
        last_page: 1,
        per_page: 25,
        total: 0,
    });
    const [registerPage, setRegisterPage] = useState(1);
    const [registerFilter, setRegisterFilter] = useState("");
    const [courseIdFilter, setCourseIdFilter] = useState("");
    const [batchIdFilter, setBatchIdFilter] = useState("");
    const [appliedRegisterFilters, setAppliedRegisterFilters] =
        useState<RegisterFilters>({
            search: "",
            courseId: "",
            batchId: "",
        });
    const [courseOptions, setCourseOptions] = useState<
        { id: number; name: string }[]
    >([]);
    const [batchOptions, setBatchOptions] = useState<
        { id: number; name: string }[]
    >([]);
    const [registerRefreshKey, setRegisterRefreshKey] = useState(0);
    const [loadingRegister, setLoadingRegister] = useState(false);
    const [loadingCharges, setLoadingCharges] = useState(false);
    const [openingRegisterPayment, setOpeningRegisterPayment] = useState(false);
    const [tuition, setTuition] = useState<Tuition | null>(null);
    const [expenses, setExpenses] = useState<Expense[]>([]);
    const [paymentOptions, setPaymentOptions] = useState<PaymentOptions>({
        cash_accounts: [],
        payment_methods: [],
    });
    const [expenseOptions, setExpenseOptions] = useState<ExpenseOption[]>([]);
    const [loadingEnrollment, setLoadingEnrollment] = useState(false);
    const [saving, setSaving] = useState(false);
    const [dialog, setDialog] = useState<"payment" | "expense" | "view" | null>(
        null,
    );
    const [selectedChargeNumber, setSelectedChargeNumber] = useState("");
    const [selectedCharge, setSelectedCharge] = useState<Charge | null>(null);
    const [selectedPayment, setSelectedPayment] =
        useState<RegisterPaymentRecord | null>(null);
    const [notice, setNotice] = useState("");
    const [error, setError] = useState("");
    const [paymentForm, setPaymentForm] = useState({
        amount: "",
        paid_on: localDate(),
        account_id: "",
        reference: "",
        notes: "",
    });
    const [paymentIdempotencyKey, setPaymentIdempotencyKey] = useState("");
    const [expenseForm, setExpenseForm] = useState({
        type_id: "",
        price_id: "",
        stage_record_id: "",
        quantity: "1.000",
        description: "",
        issued_on: localDate(),
        due_on: "",
        notes: "",
    });
    const expenseOptionsController = useRef<AbortController | null>(null);

    const selectedEnrollment =
        enrollments.find((item) => item.id === enrollmentId) ?? null;
    const charges = useMemo<Charge[]>(() => {
        const tuitionCharge: Charge[] = tuition
            ? [
                  {
                      id: tuition.id,
                      kind: "tuition",
                      enrollmentId: enrollmentId ?? undefined,
                      number: tuition.tuition_number,
                      category: "Tuition",
                      description: "Course tuition",
                      issuedOn: tuition.issued_on,
                      amount: tuition.payable_amount,
                      paid: tuition.paid_amount,
                      due: tuition.outstanding_balance,
                      payments: tuition.payments,
                      cancelled: tuition.is_cancelled,
                  },
              ]
            : [];
        const expenseCharges = expenses.map((expense): Charge => ({
            id: expense.id,
            kind: "expense",
            enrollmentId: enrollmentId ?? undefined,
            number: expense.expense_number,
            category: expense.student_expense_type?.name ?? "Other expense",
            description: expense.description,
            issuedOn: expense.issued_on,
            amount: expense.payable_amount,
            paid: expense.paid_amount,
            due: expense.outstanding_balance,
            payments: expense.payments,
            cancelled: expense.is_cancelled,
        }));
        return [...tuitionCharge, ...expenseCharges].sort((left, right) =>
            right.issuedOn.localeCompare(left.issuedOn),
        );
    }, [expenses, tuition]);
    const currentExpenseOption = expenseOptions.find(
        (option) => String(option.id) === expenseForm.type_id,
    );
    const currentPrice = currentExpenseOption?.prices.find(
        (price) => String(price.id) === expenseForm.price_id,
    );
    const issuerName = auth.user.full_name || auth.user.name;
    const branchOffice = `CMUD ${selectedEnrollment?.branch?.name ?? auth.user.branch?.name ?? "Institute-wide"} Branch Office`;
    const instituteContact = [branding?.phone, branding?.email]
        .filter(Boolean)
        .join(" · ");

    /** Fetch all records for the selected enrollment; abort stale requests on selection changes. */
    const loadEnrollment = useCallback(
        async (id: number, signal: AbortSignal) => {
            setLoadingEnrollment(true);
            setError("");
            setPaymentOptions({ cash_accounts: [], payment_methods: [] });
            try {
                const base = `/enrollments/${id}`;
                const [expenseResponse, optionsResponse, tuitionResponse] =
                    await Promise.all([
                        request<Envelope<Expense[]>>(`${base}/expenses`, {
                            signal,
                        }),
                        request<Envelope<PaymentOptions>>(
                            `${base}/payment-options`,
                            { signal },
                        ),
                        request<Envelope<Tuition>>(`${base}/tuition`, {
                            signal,
                        }).catch((reason: unknown) => {
                            if (
                                reason instanceof ApiRequestError &&
                                reason.status === 404
                            )
                                return null;
                            throw reason;
                        }),
                    ]);
                setExpenses(expenseResponse.data.data);
                setPaymentOptions(optionsResponse.data.data);
                setTuition(tuitionResponse?.data.data ?? null);
            } catch (reason) {
                if (signal.aborted) return;
                setError(
                    reason instanceof Error
                        ? reason.message
                        : "Unable to load this enrollment’s billing records.",
                );
                setExpenses([]);
                setTuition(null);
                setPaymentOptions({ cash_accounts: [], payment_methods: [] });
            } finally {
                if (!signal.aborted) setLoadingEnrollment(false);
            }
        },
        [request],
    );

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
                if (filters.courseId) query.set("course_id", filters.courseId);
                if (filters.batchId) query.set("batch_id", filters.batchId);
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
                        recorded_by_name: row.recorded_by_name,
                        source: row.source,
                        student: row.student,
                        enrollment: row.enrollment,
                        charge: {
                            id: row.charge.id,
                            kind: row.charge.kind,
                            enrollmentId: row.enrollment.id,
                            number: row.charge.number,
                            category: row.charge.category,
                            description: row.charge.description,
                            issuedOn: row.charge.issued_on,
                            amount: row.charge.amount,
                            paid: row.charge.paid,
                            due: row.charge.due,
                            payments: [],
                            cancelled: false,
                        },
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

    /** Keep due obligations available to the payment dialog without mixing them into payment records. */
    const loadChargeRegister = useCallback(
        async (signal: AbortSignal, filters: RegisterFilters) => {
            setLoadingCharges(true);
            try {
                const query = new URLSearchParams({
                    page: "1",
                    per_page: "100",
                });
                if (filters.search) query.set("search", filters.search);
                if (filters.courseId) query.set("course_id", filters.courseId);
                if (filters.batchId) query.set("batch_id", filters.batchId);
                const response = await request<{
                    data: RegisterChargeApi[];
                }>(`/student-payment-charges?${query.toString()}`, { signal });
                if (signal.aborted) return;
                setChargeRows(
                    response.data.data.map((row) => ({
                        id: row.id,
                        kind: row.kind,
                        enrollmentId: row.enrollment.id,
                        number: row.number,
                        category: row.category,
                        description: row.description,
                        issuedOn: row.issued_on,
                        amount: row.payable_amount,
                        payable_amount: row.payable_amount,
                        paid: row.paid_amount,
                        due: row.outstanding_balance,
                        payments: row.payments,
                        cancelled: row.is_cancelled,
                        student: row.student,
                        enrollment: row.enrollment,
                    })),
                );
            } catch (reason) {
                if (!signal.aborted) {
                    setChargeRows([]);
                    setError(
                        getRequestErrorMessage(
                            reason,
                            "Unable to load unpaid student charges.",
                        ),
                    );
                }
            } finally {
                if (!signal.aborted) setLoadingCharges(false);
            }
        },
        [request],
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

    useEffect(() => {
        const controller = new AbortController();
        void loadChargeRegister(controller.signal, appliedRegisterFilters);
        return () => controller.abort();
    }, [appliedRegisterFilters, loadChargeRegister, registerRefreshKey]);

    useEffect(() => {
        const controller = new AbortController();
        void request<
            Envelope<{
                courses: { id: number; name: string }[];
                batches: { id: number; name: string }[];
            }>
        >(
            `/student-payments/options${courseIdFilter ? `?course_id=${courseIdFilter}` : ""}`,
            { signal: controller.signal },
        )
            .then((response) => {
                if (!controller.signal.aborted) {
                    if (!courseIdFilter)
                        setCourseOptions(response.data.data.courses);
                    setBatchOptions(response.data.data.batches);
                }
            })
            .catch((reason: unknown) => {
                if (!controller.signal.aborted)
                    setError(
                        getRequestErrorMessage(
                            reason,
                            "Unable to load course and batch filters.",
                        ),
                    );
            });
        return () => controller.abort();
    }, [courseIdFilter, request]);

    useEffect(() => {
        if (!enrollmentId) {
            setLoadingEnrollment(false);
            setExpenses([]);
            setTuition(null);
            setPaymentOptions({ cash_accounts: [], payment_methods: [] });
            return;
        }
        const controller = new AbortController();
        void loadEnrollment(enrollmentId, controller.signal);
        return () => controller.abort();
    }, [enrollmentId, loadEnrollment]);

    useEffect(() => {
        if (dialog !== "expense") {
            expenseOptionsController.current?.abort();
            expenseOptionsController.current = null;
        }
    }, [dialog]);

    useEffect(() => () => expenseOptionsController.current?.abort(), []);

    async function refreshSelectedEnrollment() {
        if (!enrollmentId) return;
        const controller = new AbortController();
        await loadEnrollment(enrollmentId, controller.signal);
    }

    /** Select the owning enrollment from a register row and load only its records. */
    async function selectRegisterEnrollment(
        row: Pick<RegisterPaymentRecord, "student" | "enrollment">,
    ) {
        try {
            const response = await request<
                Envelope<{ student: Student; enrollments: Enrollment[] }>
            >(
                `/students/search?student_id=${encodeURIComponent(row.student.student_code)}`,
            );
            const visibleEnrollment = response.data.data.enrollments.find(
                (enrollment) => enrollment.id === row.enrollment.id,
            );
            if (!visibleEnrollment) {
                setError(
                    "This enrollment is no longer available in your branch scope.",
                );
                return false;
            }
            setStudent(response.data.data.student);
            setEnrollments(response.data.data.enrollments);
            setEnrollmentId(visibleEnrollment.id);
            return true;
        } catch (reason) {
            setError(
                getRequestErrorMessage(
                    reason,
                    "Unable to open this student enrollment.",
                ),
            );
            return false;
        }
    }

    /** Select a charge's scoped enrollment before opening its details dialog. */
    async function openRegisterCharge(row: RegisterPaymentRecord) {
        if (!(await selectRegisterEnrollment(row))) return;
        setSelectedCharge(row.charge);
        setSelectedPayment(row);
        setDialog("view");
    }

    /** Load branch-compatible cash accounts before opening payment from the register. */
    async function openRegisterPayment(row: RegisterCharge) {
        setOpeningRegisterPayment(true);
        setError("");
        try {
            const selected = await selectRegisterEnrollment(row);
            if (!selected) return;
            const response = await request<Envelope<PaymentOptions>>(
                `/enrollments/${row.enrollment.id}/payment-options`,
            );
            setPaymentOptions(response.data.data);
            setSelectedCharge(row);
            setSelectedPayment(null);
            setPaymentForm({
                amount: "",
                paid_on: localDate(),
                account_id: "",
                reference: "",
                notes: "",
            });
            setPaymentIdempotencyKey(makeIdempotencyKey());
            setDialog("payment");
        } catch (reason) {
            setError(
                getRequestErrorMessage(
                    reason,
                    "Unable to load cash accounts for this enrollment.",
                ),
            );
        } finally {
            setOpeningRegisterPayment(false);
        }
    }

    async function openExpenseDialog() {
        if (!enrollmentId) return;
        expenseOptionsController.current?.abort();
        const controller = new AbortController();
        expenseOptionsController.current = controller;
        setDialog("expense");
        setExpenseForm({
            type_id: "",
            price_id: "",
            stage_record_id: "",
            quantity: "1.000",
            description: "",
            issued_on: localDate(),
            due_on: "",
            notes: "",
        });
        setExpenseOptions([]);
        setError("");
        try {
            const response = await request<Envelope<ExpenseOption[]>>(
                `/enrollments/${enrollmentId}/expenses/options?issued_on=${localDate()}`,
                { signal: controller.signal },
            );
            if (!controller.signal.aborted) {
                setExpenseOptions(response.data.data);
            }
        } catch (reason) {
            if (!controller.signal.aborted) {
                setError(
                    getRequestErrorMessage(
                        reason,
                        "Unable to load approved expense prices.",
                    ),
                );
            }
        }
    }

    async function updateExpenseOptions(
        issuedOn: string,
        stageRecordId = expenseForm.stage_record_id,
    ) {
        if (!enrollmentId) return;
        expenseOptionsController.current?.abort();
        const controller = new AbortController();
        expenseOptionsController.current = controller;
        setExpenseOptions([]);
        setExpenseForm((current) => ({
            ...current,
            issued_on: issuedOn,
            stage_record_id: stageRecordId,
            type_id: "",
            price_id: "",
        }));
        try {
            const query = new URLSearchParams({ issued_on: issuedOn });
            if (stageRecordId) {
                query.set("student_stage_record_id", stageRecordId);
            }
            const response = await request<Envelope<ExpenseOption[]>>(
                `/enrollments/${enrollmentId}/expenses/options?${query.toString()}`,
                { signal: controller.signal },
            );
            if (!controller.signal.aborted) {
                setExpenseOptions(response.data.data);
            }
        } catch (reason) {
            if (!controller.signal.aborted) {
                setError(
                    getRequestErrorMessage(
                        reason,
                        "Unable to load approved expense prices.",
                    ),
                );
            }
        }
    }

    function openPayment(charge?: Charge) {
        setSelectedCharge(charge ?? null);
        setSelectedPayment(null);
        setSelectedChargeNumber(charge?.number ?? "");
        setPaymentIdempotencyKey(makeIdempotencyKey());
        setPaymentForm({
            amount: "",
            paid_on: localDate(),
            account_id: "",
            reference: "",
            notes: "",
        });
        setError("");
        setDialog("payment");
    }

    async function submitPayment(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const charge =
            selectedCharge ??
            charges.find((item) => item.number === selectedChargeNumber);
        const targetEnrollmentId = charge?.enrollmentId ?? enrollmentId;
        if (!charge || !targetEnrollmentId) {
            setError("Select a tuition or expense charge.");
            return;
        }
        setSaving(true);
        setError("");
        try {
            // One request key makes transport retries safe without duplicating the receipt.
            const payload = {
                amount: paymentForm.amount,
                financial_account_id: Number(paymentForm.account_id),
                idempotency_key: paymentIdempotencyKey,
                paid_at: paymentForm.paid_on,
                transaction_reference: paymentForm.reference || null,
                notes: paymentForm.notes || null,
            };
            const path =
                charge.kind === "tuition"
                    ? `/enrollments/${targetEnrollmentId}/tuition/${charge.id}/payments`
                    : `/enrollments/${targetEnrollmentId}/expenses/${charge.id}/payments`;
            const response = await request<
                Envelope<{ idempotent_replay?: boolean }>
            >(path, { method: "POST", data: payload });
            setDialog(null);
            setNotice(
                response.status === 200 || response.data.data.idempotent_replay
                    ? "This payment was already recorded. The existing payment record is available in the register."
                    : "Cash payment recorded successfully.",
            );
            setRegisterRefreshKey((current) => current + 1);
            const controller = new AbortController();
            await loadEnrollment(targetEnrollmentId, controller.signal);
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
        if (!enrollmentId) return;
        setSaving(true);
        setError("");
        try {
            await request<Envelope<Expense>>(
                `/enrollments/${enrollmentId}/expenses`,
                {
                    method: "POST",
                    data: {
                        student_expense_type_id: Number(expenseForm.type_id),
                        price_version_id: Number(expenseForm.price_id),
                        student_stage_record_id: expenseForm.stage_record_id
                            ? Number(expenseForm.stage_record_id)
                            : null,
                        quantity: expenseForm.quantity,
                        description: expenseForm.description || undefined,
                        issued_on: expenseForm.issued_on,
                        due_on: expenseForm.due_on || null,
                        notes: expenseForm.notes || null,
                    },
                },
            );
            setDialog(null);
            setNotice("Student expense issued successfully.");
            await refreshSelectedEnrollment();
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
        charge: Charge,
        payment: Payment,
        registerContext?: {
            student: Student;
            courseName: string | null;
            batchName: string | null;
            branchName: string | null;
            paidToDate: string;
            due: string;
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
            ["Issued by", payment.recorded_by_name ?? issuerName],
            ["Charge reference", charge.number],
            ...(payment.transaction_reference
                ? [["Transaction reference", payment.transaction_reference]]
                : []),
        ]
            .map(
                ([label, value]) =>
                    `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></div>`,
            )
            .join("");
        const collected = registerContext
            ? Number(registerContext.paidToDate)
            : charge.payments
                  .filter(
                      (item) =>
                          item.status === "confirmed" &&
                          new Date(item.paid_at) <= new Date(payment.paid_at),
                  )
                  .reduce((total, item) => total + Number(item.amount), 0);
        const receiptDue = registerContext
            ? Number(registerContext.due)
            : Math.max(0, Number(charge.amount) - collected);
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
            : branchOffice;

        printWindow.document.open();
        printWindow.document
            .write(`<!doctype html><html><head><meta charset="utf-8"><title>Payment receipt ${escapeHtml(payment.payment_number)}</title><style>
            *{box-sizing:border-box}body{margin:0;color:#111827;font:12px Arial,sans-serif}.receipt{width:100%;padding:32px 40px}.header{display:flex;justify-content:space-between;gap:24px;border-bottom:2px solid #17375c;padding-bottom:18px}.brand img{display:block;width:auto;height:48px;max-width:190px;object-fit:contain;object-position:left}.brand p{margin:5px 0 0;color:#64748b;font-size:11px}.header-title{text-align:right}.header-title h1{margin:0;color:#17375c;font-size:22px}.header-title p{margin:5px 0 0;color:#64748b;font-size:10px;font-weight:bold;letter-spacing:.16em;text-transform:uppercase}.content{display:grid;grid-template-columns:1fr 1fr;gap:30px;margin-top:24px}.label{margin:0 0 8px;color:#64748b;font-size:10px;font-weight:bold;letter-spacing:.14em;text-transform:uppercase}.student-name{margin:0;font-size:15px;font-weight:bold}.student-extra{margin:5px 0 0;color:#475569;font-size:11px}.meta{display:grid;grid-template-columns:1fr 1fr;gap:10px 16px;margin:0;padding:13px;border:1px solid #e2e8f0;border-radius:6px}.meta dt{color:#64748b;font-size:9px}.meta dd{margin:3px 0 0;font-size:11px;font-weight:bold;overflow-wrap:anywhere}.items{margin-top:26px}.items-heading{display:flex;justify-content:space-between;align-items:end;margin-bottom:8px}.items-heading h2{margin:0;font-size:14px}.items-heading span{color:#64748b;font-size:10px}table{width:100%;border-collapse:collapse;font-size:11px}th{border-top:1px solid #cbd5e1;border-bottom:1px solid #cbd5e1;background:#f8fafc;padding:9px 10px;text-align:left}td{border-bottom:1px solid #e2e8f0;padding:12px 10px}th:last-child,td:last-child{text-align:right}.totals{display:flex;justify-content:flex-end;margin-top:18px}.totals dl{width:260px;margin:0}.total-row{display:flex;justify-content:space-between;gap:20px;padding:6px 0}.total-row dt{color:#475569}.total-row dd{margin:0;font-variant-numeric:tabular-nums}.total-row.final{margin-top:4px;border-top:2px solid #17375c;padding-top:9px;font-size:13px;font-weight:bold}.signatures{display:flex;justify-content:space-between;gap:48px;margin-top:58px}.signature-block{width:220px;text-align:center}.signature-name{min-height:16px;font-size:11px;font-weight:bold}.signature-line{margin-top:28px;border-top:1px solid #94a3b8;padding-top:7px;color:#475569;font-size:10px}.footer{margin-top:28px;border-top:1px solid #e2e8f0;padding-top:9px;color:#64748b;font-size:9px;text-align:center}@page{size:A4;margin:14mm}@media print{.receipt{padding:0}.header,.content,.items,table,.totals,.signatures,.footer{break-inside:avoid}}
            </style></head><body><main class="receipt"><header class="header"><div class="brand"><img src="${logoUrl}" alt="${escapeHtml(branding?.name ?? "CMUD")} logo"><p>${escapeHtml(receiptBranchOffice)}</p>${instituteContact ? `<p>${escapeHtml(instituteContact)}</p>` : ""}</div><div class="header-title"><h1>PAYMENT RECEIPT</h1><p>Student payment record</p></div></header><section class="content"><div><h2 class="label">Student</h2><p class="student-name">${escapeHtml(receiptStudent?.full_name ?? "Student")}</p><p class="student-extra">Student ID: ${escapeHtml(receiptStudent?.student_code ?? "")}</p><p class="student-extra">Course: ${escapeHtml(receiptCourseName ?? "—")}</p><p class="student-extra">Batch: ${escapeHtml(receiptBatchName ?? "—")}</p></div><dl class="meta">${receiptMetadata}</dl></section><section class="items"><div class="items-heading"><h2>${escapeHtml(charge.description)}</h2><span>Currency: BDT</span></div><table><thead><tr><th>#</th><th>Payment detail</th><th>Amount received</th></tr></thead><tbody><tr><td>1</td><td>${escapeHtml(charge.category)} payment</td><td>${escapeHtml(money(payment.amount))}</td></tr></tbody></table></section><section class="totals"><dl><div class="total-row"><dt>Charge total</dt><dd>${escapeHtml(money(charge.amount))}</dd></div><div class="total-row"><dt>Paid to date</dt><dd>${escapeHtml(money(collected))}</dd></div><div class="total-row final"><dt>Balance due</dt><dd>${escapeHtml(money(receiptDue))}</dd></div></dl></section><section class="signatures"><div class="signature-block"><div class="signature-name">${escapeHtml(issuer)}</div><div class="signature-line">Issued by / Signature</div></div><div class="signature-block"><div class="signature-name">${escapeHtml(receiptStudent?.full_name ?? "Student")}</div><div class="signature-line">Received by / Student signature</div></div></section></main><script>window.addEventListener('load',function(){window.focus();window.print();});window.addEventListener('afterprint',function(){window.close();});</script></body></html>`);
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
                                Find student payment records and record cash
                                receipts.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => void openExpenseDialog()}
                                disabled={!enrollmentId}
                                className="inline-flex items-center gap-2 rounded-xl border border-white/30 px-4 py-3 text-sm font-semibold text-white hover:bg-white/10 disabled:opacity-50"
                            >
                                <Plus size={17} /> Issue expense
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    if (enrollmentId) {
                                        openPayment();
                                        return;
                                    }
                                    const firstDueCharge = chargeRows.find(
                                        (charge) =>
                                            !charge.cancelled &&
                                            Number(charge.due) > 0,
                                    );
                                    if (firstDueCharge)
                                        void openRegisterPayment(
                                            firstDueCharge,
                                        );
                                }}
                                disabled={
                                    loadingCharges ||
                                    openingRegisterPayment ||
                                    (enrollmentId
                                        ? charges.every(
                                              (charge) =>
                                                  charge.cancelled ||
                                                  Number(charge.due) <= 0,
                                          )
                                        : !chargeRows.some(
                                              (charge) =>
                                                  !charge.cancelled &&
                                                  Number(charge.due) > 0,
                                          ))
                                }
                                className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-[#17375C] hover:bg-cyan-50 disabled:opacity-50"
                            >
                                <CreditCard size={17} /> Record payment
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
                                    Search by student ID or name, then narrow
                                    the list by course and batch.
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setRegisterPage(1);
                                        setAppliedRegisterFilters({
                                            search: registerFilter.trim(),
                                            courseId: courseIdFilter,
                                            batchId: batchIdFilter,
                                        });
                                    }}
                                    className={primary}
                                >
                                    <Search size={16} />
                                    Search
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setRegisterFilter("");
                                        setCourseIdFilter("");
                                        setBatchIdFilter("");
                                        setBatchOptions([]);
                                        setRegisterPage(1);
                                        setAppliedRegisterFilters({
                                            search: "",
                                            courseId: "",
                                            batchId: "",
                                        });
                                    }}
                                    className="rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                                >
                                    Clear filters
                                </button>
                            </div>
                        </div>
                        <div className="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
                                            setRegisterPage(1);
                                        }}
                                        placeholder="Search student by ID or name"
                                        className={`${field} mt-0 pl-9`}
                                    />
                                </span>
                            </label>
                            <label className="block text-xs font-medium text-slate-600">
                                Course
                                <select
                                    value={courseIdFilter}
                                    onChange={(event) => {
                                        setCourseIdFilter(event.target.value);
                                        setBatchIdFilter("");
                                        setBatchOptions([]);
                                        setRegisterPage(1);
                                    }}
                                    className={field}
                                >
                                    <option value="">All courses</option>
                                    {courseOptions.map((course) => (
                                        <option
                                            key={course.id}
                                            value={course.id}
                                        >
                                            {course.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="block text-xs font-medium text-slate-600">
                                Batch
                                <select
                                    value={batchIdFilter}
                                    onChange={(event) => {
                                        setBatchIdFilter(event.target.value);
                                        setRegisterPage(1);
                                    }}
                                    disabled={!courseIdFilter}
                                    className={field}
                                >
                                    <option value="">All batches</option>
                                    {batchOptions.map((batch) => (
                                        <option key={batch.id} value={batch.id}>
                                            {batch.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
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
                                {registerFilter && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setRegisterFilter("");
                                            setRegisterPage(1);
                                            setAppliedRegisterFilters(
                                                (filters) => ({
                                                    ...filters,
                                                    search: "",
                                                }),
                                            );
                                        }}
                                        className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50"
                                    >
                                        Clear student search
                                    </button>
                                )}
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
                                            "Charge",
                                            "Student",
                                            "Payment date",
                                            "Amount",
                                            "Method",
                                            "Issued by",
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
                                    {registerRows.map((record) => (
                                        <tr key={record.payment_key}>
                                            <td className="px-5 py-4 font-mono text-xs whitespace-nowrap text-[#17375C]">
                                                {record.payment_number}
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-mono text-xs whitespace-nowrap text-[#17375C]">
                                                    {record.charge.number}
                                                </p>
                                                <p className="mt-1 min-w-40 text-xs text-slate-600">
                                                    {record.charge.description}
                                                </p>
                                                <p className="mt-1 text-xs text-slate-400">
                                                    {record.charge.category}
                                                </p>
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
                                            <td className="px-5 py-4 text-xs whitespace-nowrap">
                                                {record.recorded_by_name ??
                                                    "N/A"}
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
                                                        onClick={() =>
                                                            void openRegisterCharge(
                                                                record,
                                                            )
                                                        }
                                                        aria-label={`View payment ${record.payment_number}`}
                                                        title="View payment"
                                                        className="rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-50"
                                                    >
                                                        <Eye size={16} />
                                                    </button>
                                                    {record.status ===
                                                        "confirmed" && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                printReceipt(
                                                                    record.charge,
                                                                    record,
                                                                    {
                                                                        student:
                                                                            record.student,
                                                                        courseName:
                                                                            record
                                                                                .enrollment
                                                                                .course_name,
                                                                        batchName:
                                                                            record
                                                                                .enrollment
                                                                                .batch_name,
                                                                        branchName:
                                                                            record
                                                                                .enrollment
                                                                                .branch_name,
                                                                        paidToDate:
                                                                            record
                                                                                .charge
                                                                                .paid,
                                                                        due: record
                                                                            .charge
                                                                            .due,
                                                                    },
                                                                )
                                                            }
                                                            aria-label={`Print payment receipt ${record.payment_number}`}
                                                            title="Print payment receipt"
                                                            className="rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-50"
                                                        >
                                                            <Printer
                                                                size={16}
                                                            />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {!registerRows.length && (
                                        <tr>
                                            <td
                                                colSpan={9}
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
                                ? "Record cash payment"
                                : dialog === "expense"
                                  ? "Issue student expense"
                                  : selectedPayment
                                    ? "Payment record details"
                                    : "Charge details"}
                        </DialogTitle>
                        <DialogDescription>
                            {dialog === "payment"
                                ? "The payment is recorded as cash against the selected enrollment charge."
                                : dialog === "expense"
                                  ? "Issue an approved expense price against this enrollment."
                                  : selectedPayment
                                    ? "Review this payment record and its linked charge."
                                    : "Review the selected student charge."}
                        </DialogDescription>
                    </DialogHeader>
                    {dialog === "payment" && (
                        <form onSubmit={submitPayment} className="space-y-4">
                            {!selectedCharge && (
                                <label className="block text-xs font-medium text-slate-600">
                                    Student charge
                                    <select
                                        required
                                        value={selectedChargeNumber}
                                        onChange={(event) =>
                                            setSelectedChargeNumber(
                                                event.target.value,
                                            )
                                        }
                                        className={field}
                                    >
                                        <option value="">
                                            Choose a charge
                                        </option>
                                        {charges
                                            .filter(
                                                (charge) =>
                                                    !charge.cancelled &&
                                                    Number(charge.due) > 0,
                                            )
                                            .map((charge) => (
                                                <option
                                                    key={`${charge.kind}-${charge.id}`}
                                                    value={charge.number}
                                                >
                                                    {charge.category} ·{" "}
                                                    {charge.description} · Due{" "}
                                                    {money(charge.due)}
                                                </option>
                                            ))}
                                    </select>
                                </label>
                            )}
                            {selectedCharge && (
                                <div className="space-y-1 rounded-xl bg-slate-50 p-4">
                                    <p className="font-semibold text-[#17375C]">
                                        {student?.full_name}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        {selectedCharge.number} ·{" "}
                                        {selectedCharge.description}
                                    </p>
                                    <p className="pt-2 text-sm">
                                        Charge: {money(selectedCharge.amount)} ·
                                        Due:{" "}
                                        <strong>
                                            {money(selectedCharge.due)}
                                        </strong>
                                    </p>
                                </div>
                            )}
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="block text-xs font-medium text-slate-600">
                                    Payment amount (BDT)
                                    <input
                                        required
                                        type="number"
                                        min="0.01"
                                        max={selectedCharge?.due}
                                        step="0.01"
                                        value={paymentForm.amount}
                                        onChange={(event) =>
                                            setPaymentForm({
                                                ...paymentForm,
                                                amount: event.target.value,
                                            })
                                        }
                                        className={field}
                                    />
                                </label>
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
                                Cash account
                                <select
                                    required
                                    value={paymentForm.account_id}
                                    onChange={(event) =>
                                        setPaymentForm({
                                            ...paymentForm,
                                            account_id: event.target.value,
                                        })
                                    }
                                    className={field}
                                >
                                    <option value="">
                                        Select a cash account
                                    </option>
                                    {paymentOptions.cash_accounts.map(
                                        (account) => (
                                            <option
                                                key={account.id}
                                                value={account.id}
                                            >
                                                {account.name}
                                                {account.branch_id === null
                                                    ? " · Institute account"
                                                    : ""}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </label>
                            <label className="block text-xs font-medium text-slate-600">
                                Transaction / reference (optional)
                                <input
                                    value={paymentForm.reference}
                                    onChange={(event) =>
                                        setPaymentForm({
                                            ...paymentForm,
                                            reference: event.target.value,
                                        })
                                    }
                                    className={field}
                                />
                            </label>
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
                            {!paymentOptions.cash_accounts.length && (
                                <p className="text-sm text-amber-700">
                                    No active cash account is configured for
                                    this enrollment.
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
                                        !paymentOptions.cash_accounts.length
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
                                Charge for stage (optional)
                                <select
                                    value={expenseForm.stage_record_id}
                                    onChange={(event) =>
                                        void updateExpenseOptions(
                                            expenseForm.issued_on,
                                            event.target.value,
                                        )
                                    }
                                    className={field}
                                >
                                    <option value="">Entire enrollment</option>
                                    {(
                                        selectedEnrollment?.stage_records ?? []
                                    ).map((stage) => (
                                        <option key={stage.id} value={stage.id}>
                                            {stage.course_stage?.name ??
                                                "Course stage"}{" "}
                                            · {stage.status}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="block text-xs font-medium text-slate-600">
                                Expense type
                                <select
                                    required
                                    value={expenseForm.type_id}
                                    onChange={(event) => {
                                        const typeId = event.target.value;
                                        const option = expenseOptions.find(
                                            (item) =>
                                                String(item.id) === typeId,
                                        );
                                        setExpenseForm({
                                            ...expenseForm,
                                            type_id: typeId,
                                            price_id: option?.prices[0]
                                                ? String(option.prices[0].id)
                                                : "",
                                        });
                                    }}
                                    className={field}
                                >
                                    <option value="">
                                        Choose an expense type
                                    </option>
                                    {expenseOptions
                                        .filter(
                                            (option) =>
                                                option.prices.length > 0,
                                        )
                                        .map((option) => (
                                            <option
                                                key={option.id}
                                                value={option.id}
                                            >
                                                {option.name}
                                            </option>
                                        ))}
                                </select>
                            </label>
                            <label className="block text-xs font-medium text-slate-600">
                                Approved price
                                <select
                                    required
                                    value={expenseForm.price_id}
                                    onChange={(event) =>
                                        setExpenseForm({
                                            ...expenseForm,
                                            price_id: event.target.value,
                                        })
                                    }
                                    disabled={
                                        !currentExpenseOption?.prices.length
                                    }
                                    className={field}
                                >
                                    <option value="">
                                        Choose an effective price
                                    </option>
                                    {currentExpenseOption?.prices.map(
                                        (price) => (
                                            <option
                                                key={price.id}
                                                value={price.id}
                                            >
                                                {money(price.amount)} ·
                                                effective{" "}
                                                {dateLabel(
                                                    price.effective_from,
                                                )}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </label>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="block text-xs font-medium text-slate-600">
                                    Quantity
                                    <input
                                        required
                                        type="number"
                                        min="0.001"
                                        step="0.001"
                                        value={expenseForm.quantity}
                                        onChange={(event) =>
                                            setExpenseForm({
                                                ...expenseForm,
                                                quantity: event.target.value,
                                            })
                                        }
                                        className={field}
                                    />
                                </label>
                                <label className="block text-xs font-medium text-slate-600">
                                    Issue date
                                    <input
                                        required
                                        type="date"
                                        value={expenseForm.issued_on}
                                        onChange={(event) =>
                                            void updateExpenseOptions(
                                                event.target.value,
                                            )
                                        }
                                        className={field}
                                    />
                                </label>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="block text-xs font-medium text-slate-600">
                                    Due date (optional)
                                    <input
                                        type="date"
                                        min={expenseForm.issued_on}
                                        value={expenseForm.due_on}
                                        onChange={(event) =>
                                            setExpenseForm({
                                                ...expenseForm,
                                                due_on: event.target.value,
                                            })
                                        }
                                        className={field}
                                    />
                                </label>
                                <div className="rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-500">
                                    Unit price:{" "}
                                    <strong className="text-slate-800">
                                        {currentPrice
                                            ? money(currentPrice.amount)
                                            : "—"}
                                    </strong>
                                    <br />
                                    Charge total uses the selected price and
                                    quantity.
                                </div>
                            </div>
                            <label className="block text-xs font-medium text-slate-600">
                                Description (optional)
                                <input
                                    value={expenseForm.description}
                                    onChange={(event) =>
                                        setExpenseForm({
                                            ...expenseForm,
                                            description: event.target.value,
                                        })
                                    }
                                    className={field}
                                />
                            </label>
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
                                        !expenseForm.type_id ||
                                        !expenseForm.price_id
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
                                    {saving ? "Issuing…" : "Issue expense"}
                                </button>
                            </div>
                        </form>
                    )}
                    {dialog === "view" && selectedCharge && (
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
                                        {selectedPayment?.status ??
                                            statusLabel(selectedCharge)}
                                    </span>
                                </div>
                                <div className="mt-4 grid gap-3 border-t border-slate-200 pt-4 sm:grid-cols-2">
                                    <div>
                                        <p className="text-xs text-slate-500">
                                            Charge
                                        </p>
                                        <p className="mt-1 text-sm font-medium">
                                            {selectedCharge.number} ·{" "}
                                            {selectedCharge.description}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-slate-500">
                                            Issue date
                                        </p>
                                        <p className="mt-1 text-sm font-medium">
                                            {dateLabel(selectedCharge.issuedOn)}
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
                            <section className="grid gap-3 sm:grid-cols-3">
                                <div className="rounded-xl border border-slate-200 p-4">
                                    <p className="text-xs text-slate-500">
                                        Payable
                                    </p>
                                    <p className="mt-1 font-semibold text-[#17375C]">
                                        {money(selectedCharge.amount)}
                                    </p>
                                </div>
                                <div className="rounded-xl border border-slate-200 p-4">
                                    <p className="text-xs text-slate-500">
                                        Paid
                                    </p>
                                    <p className="mt-1 font-semibold text-emerald-700">
                                        {money(selectedCharge.paid)}
                                    </p>
                                </div>
                                <div className="rounded-xl border border-slate-200 p-4">
                                    <p className="text-xs text-slate-500">
                                        Due
                                    </p>
                                    <p className="mt-1 font-semibold text-amber-700">
                                        {money(selectedCharge.due)}
                                    </p>
                                </div>
                            </section>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
