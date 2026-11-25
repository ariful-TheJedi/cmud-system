import {
    useCallback,
    useEffect,
    useMemo,
    useState,
    type FormEvent,
} from 'react';
import { Head, usePage } from '@inertiajs/react';
import { createPortal } from 'react-dom';
import {
    CalendarDays,
    Banknote,
    Eye,
    FileText,
    FilterX,
    LoaderCircle,
    Pencil,
    Plus,
    Printer,
    ReceiptText,
    Search,
    Save,
    X,
} from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ApiRequestError, useApi } from '@/hooks/use-api';
import AppLayout from '@/layouts/app-layout';
import type { Auth } from '@/types/auth';

type BillLine = {
    item_name: string;
    description: string | null;
    quantity: number | string;
    unit_name: string | null;
    unit_amount: string;
    total: string;
};
type BillingRecord = {
    id: number;
    expense_number: string;
    billing_name: string;
    type: {
        id: number;
        institute_id: number;
        name: string;
        category: string | null;
    } | null;
    payee_name: string;
    invoice_number: string | null;
    branch: { id: number; name: string; code: string | null } | null;
    department: { id: number; name: string; code: string | null } | null;
    billing_date: string;
    due_date: string | null;
    amount: string;
    paid_amount: string;
    balance: string;
    payment_status: 'unpaid' | 'partial' | 'paid';
    approval_status: string;
    created_by_id: number | null;
    created_by_name: string | null;
    created_by_designation: string | null;
    created_by_employee_id: string | null;
    items: BillLine[];
    payments: Array<{
        id: number;
        payment_number: string;
        amount: string;
        paid_at: string;
        payment_method: string | null;
        financial_account: string | null;
        transaction_reference: string | null;
        status: string;
    }>;
    notes: string | null;
};
type BillTypeOption = {
    id: number;
    name: string;
};
type BillItemOption = {
    id: number;
    code: string;
    name: string;
    is_other: boolean;
    institute_id: number | null;
    prices: Array<{
        id: number;
        branch_id: number | null;
        amount: string;
        unit_name: string | null;
        effective_from: string;
        effective_to: string | null;
    }>;
};
type BranchOption = {
    id: number;
    institute_id: number;
    name: string;
    code: string;
};
type DepartmentOption = {
    id: number;
    institute_id: number;
    name: string;
    code: string;
};
type CashAccountOption = {
    id: number;
    institute_id: number;
    branch_id: number | null;
    name: string;
    code: string;
};
type ApiEnvelope<T> = { data: T; message?: string };
type BillRegister = ApiEnvelope<BillingRecord[]> & {
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};
type BillOptions = {
    types: BillTypeOption[];
    branches: BranchOption[];
    departments: DepartmentOption[];
    cash_accounts: CashAccountOption[];
    payees: string[];
};
type DateFilterMode = 'single' | 'range';
type BillForm = {
    billName: string;
    billingItemId: string;
    priceVersionId: string;
    payee: string;
    customPayee: string;
    branchId: string;
    billingDate: string;
    dueDate: string;
    status: 'pending' | 'approved' | 'cancelled';
    cashPayment: boolean;
    cashPaymentAmount: string;
    cashAccountId: string;
    amount: string;
    quantity: string;
    description: string;
    notes: string;
};
type ApprovalStatus = 'draft' | 'pending' | 'approved' | 'cancelled';

const emptyForm: BillForm = {
    billName: '',
    billingItemId: '',
    priceVersionId: '',
    payee: '',
    customPayee: '',
    branchId: '',
    billingDate: '',
    dueDate: '',
    status: 'pending',
    cashPayment: false,
    cashPaymentAmount: '',
    cashAccountId: '',
    amount: '',
    quantity: '1',
    description: '',
    notes: '',
};
const money = (amount: string | number) =>
    `৳ ${new Intl.NumberFormat('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(amount) || 0)}`;
/** Render whole bill quantities with three trailing zeroes. */
const formatQuantity = (quantity: number | string) =>
    new Intl.NumberFormat('en-BD', {
        maximumFractionDigits: 3,
    }).format(Number(quantity) || 0);
/** Combine a quantity and its configured unit for bill summaries. */
const quantityWithUnit = (line: BillLine) =>
    `${formatQuantity(line.quantity)}${line.unit_name ? ` ${line.unit_name}` : ''}`;
const inputClass =
    'mt-1.5 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-600/10';
const primaryButton =
    'inline-flex items-center justify-center gap-2 rounded-xl bg-[#17375C] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#254878] disabled:cursor-not-allowed disabled:opacity-60';

/** Convert Laravel and network errors into useful messages for the page and form. */
function requestErrorMessage(error: unknown): string {
    if (error instanceof ApiRequestError) {
        const validationMessages = Object.values(error.errors ?? {}).flat();
        return validationMessages.length
            ? `${error.message} ${validationMessages.join(' ')}`
            : error.message;
    }

    return error instanceof Error
        ? error.message
        : 'The request could not be completed.';
}

/** Use the Finance API as the sole source of Institute Billing records and options. */
export default function InstituteBillingWorkspace() {
    const { request } = useApi();
    const { auth, branding } = usePage<{
        auth: Auth;
        branding?: {
            phone: string | null;
            email: string | null;
            logo_url: string;
            name: string;
        };
    }>().props;
    const currentUser = auth.user;
    const isSuperUser = currentUser.is_super_admin === true;
    const currentBranchId =
        !isSuperUser && typeof currentUser.branch_id === 'number'
            ? currentUser.branch_id
            : null;
    const [records, setRecords] = useState<BillingRecord[]>([]);
    const [options, setOptions] = useState<BillOptions>({
        types: [],
        branches: [],
        departments: [],
        cash_accounts: [],
        payees: [],
    });
    const [billingItems, setBillingItems] = useState<BillItemOption[]>([]);
    const [selectedBillingItem, setSelectedBillingItem] =
        useState<BillItemOption | null>(null);
    const [billingItemSearch, setBillingItemSearch] = useState('');
    const [billingItemListOpen, setBillingItemListOpen] = useState(false);
    const [billingItemsLoading, setBillingItemsLoading] = useState(false);
    const [recordId, setRecordId] = useState('');
    const [billingName, setBillingName] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [dateMode, setDateMode] = useState<DateFilterMode>('single');
    const [singleDate, setSingleDate] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [dialog, setDialog] = useState<
        'create' | 'details' | 'status' | 'payment' | null
    >(null);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [selectedRecord, setSelectedRecord] = useState<BillingRecord | null>(
        null,
    );
    const [form, setForm] = useState<BillForm>(emptyForm);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [totalRecords, setTotalRecords] = useState(0);
    const [optionsLoading, setOptionsLoading] = useState(true);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [statusSaving, setStatusSaving] = useState(false);
    const [paymentSaving, setPaymentSaving] = useState(false);
    const [detailsLoading, setDetailsLoading] = useState(false);
    const [requestError, setRequestError] = useState('');
    const [formError, setFormError] = useState('');
    const [notice, setNotice] = useState('');
    const [refreshKey, setRefreshKey] = useState(0);

    // Form options come from active Finance types and the real institute locations.
    useEffect(() => {
        const controller = new AbortController();
        setOptionsLoading(true);
        request<ApiEnvelope<BillOptions>>('/institute-bills/options', {
            signal: controller.signal,
        })
            .then(({ data }) => {
                if (!controller.signal.aborted) setOptions(data.data);
            })
            .catch((error: unknown) => {
                if (!controller.signal.aborted)
                    setRequestError(requestErrorMessage(error));
            })
            .finally(() => {
                if (!controller.signal.aborted) setOptionsLoading(false);
            });

        return () => controller.abort();
    }, [request]);

    // Search active billing items on the server and cancel older requests as the query changes.
    useEffect(() => {
        if (dialog !== 'create') return;

        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            setBillingItemsLoading(true);
            request<ApiEnvelope<BillItemOption[]>>(
                '/institute-bills/billing-items',
                {
                    params: { search: billingItemSearch.trim() },
                    signal: controller.signal,
                },
            )
                .then(({ data }) => {
                    if (controller.signal.aborted) return;
                    setBillingItems(data.data);
                    setSelectedBillingItem((current) =>
                        current
                            ? (data.data.find(
                                  (item) => item.id === current.id,
                              ) ?? current)
                            : null,
                    );
                })
                .catch((error: unknown) => {
                    if (!controller.signal.aborted) {
                        setBillingItems([]);
                        setFormError(requestErrorMessage(error));
                    }
                })
                .finally(() => {
                    if (!controller.signal.aborted)
                        setBillingItemsLoading(false);
                });
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [billingItemSearch, dialog, request]);

    // The server applies search filters and pagination; abort stale requests as filters change.
    useEffect(() => {
        const controller = new AbortController();
        const params: Record<string, string | number> = { page, per_page: 15 };
        if (recordId.trim()) params.id = recordId.trim();
        if (billingName.trim()) params.billing_name = billingName.trim();
        if (typeFilter) params.type = typeFilter;
        if (dateMode === 'single' && singleDate) params.date = singleDate;
        if (dateMode === 'range') {
            if (dateFrom) params.date_from = dateFrom;
            if (dateTo) params.date_to = dateTo;
        }

        setLoading(true);
        setRequestError('');
        request<BillRegister>('/institute-bills', {
            params,
            signal: controller.signal,
        })
            .then(({ data }) => {
                if (controller.signal.aborted) return;
                setRecords(data.data);
                setLastPage(data.meta.last_page);
                setTotalRecords(data.meta.total);
            })
            .catch((error: unknown) => {
                if (!controller.signal.aborted) {
                    setRecords([]);
                    setRequestError(requestErrorMessage(error));
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [
        billingName,
        dateFrom,
        dateMode,
        dateTo,
        page,
        recordId,
        refreshKey,
        request,
        singleDate,
        typeFilter,
    ]);

    const typeOptions = useMemo(() => options.types, [options.types]);
    const currentBranch =
        options.branches.find((branch) => branch.id === currentBranchId) ??
        null;
    const selectedItem =
        selectedBillingItem?.id === Number(form.billingItemId)
            ? selectedBillingItem
            : null;
    const cashAccountOptions = useMemo(
        () =>
            options.cash_accounts.filter(
                (account) =>
                    account.institute_id === selectedItem?.institute_id &&
                    (!form.branchId ||
                        account.branch_id === null ||
                        String(account.branch_id) === form.branchId),
            ),
        [form.branchId, options.cash_accounts, selectedItem?.institute_id],
    );
    const selectedItemPriceOptions = useMemo(
        () =>
            (selectedItem?.prices ?? []).filter(
                (price) =>
                    (price.branch_id === null ||
                        String(price.branch_id) === form.branchId) &&
                    price.effective_from <= form.billingDate &&
                    (!price.effective_to ||
                        price.effective_to >= form.billingDate),
            ),
        [form.billingDate, form.branchId, selectedItem],
    );
    const preferredPrice =
        selectedItemPriceOptions.find(
            (price) =>
                form.branchId && String(price.branch_id) === form.branchId,
        ) ?? selectedItemPriceOptions.find((price) => price.branch_id === null);
    const selectedPrice =
        selectedItemPriceOptions.find(
            (price) => String(price.id) === form.priceVersionId,
        ) ?? preferredPrice;
    const billTotalAmount = selectedItem?.is_other
        ? Number(form.amount || 0) * Number(form.quantity || 0)
        : Number(selectedPrice?.amount || 0) * Number(form.quantity || 0);
    const selectedRecordCashAccounts = useMemo(
        () =>
            options.cash_accounts.filter(
                (account) =>
                    account.institute_id ===
                        selectedRecord?.type?.institute_id &&
                    (selectedRecord.branch
                        ? account.branch_id === null ||
                          account.branch_id === selectedRecord.branch.id
                        : true),
            ),
        [
            options.cash_accounts,
            selectedRecord?.branch,
            selectedRecord?.type?.institute_id,
        ],
    );

    // Keep the selected price in sync whenever category, branch, or billing date changes.
    useEffect(() => {
        if (selectedItem?.is_other) return;
        const nextPriceId = String(preferredPrice?.id ?? '');
        if (form.priceVersionId !== nextPriceId) {
            setForm((current) => ({ ...current, priceVersionId: nextPriceId }));
        }
    }, [form.priceVersionId, preferredPrice, selectedItem?.is_other]);

    // Keep the selected cash account valid when the billing item or institute changes.
    useEffect(() => {
        if (!form.cashPayment) return;

        const accountIsAvailable = cashAccountOptions.some(
            (account) => String(account.id) === form.cashAccountId,
        );
        if (!accountIsAvailable) {
            const nextAccountId = String(cashAccountOptions[0]?.id ?? '');
            if (form.cashAccountId !== nextAccountId) {
                setForm((current) => ({
                    ...current,
                    cashAccountId: nextAccountId,
                }));
            }
        }
    }, [cashAccountOptions, form.cashAccountId, form.cashPayment]);

    // Fetch full payment and detail information only when the user opens a specific bill.
    useEffect(() => {
        if (dialog !== 'details' || selectedId === null) return;
        const controller = new AbortController();
        setSelectedRecord(null);
        setDetailsLoading(true);
        setFormError('');
        request<ApiEnvelope<BillingRecord>>(`/institute-bills/${selectedId}`, {
            signal: controller.signal,
        })
            .then(({ data }) => {
                if (!controller.signal.aborted) setSelectedRecord(data.data);
            })
            .catch((error: unknown) => {
                if (!controller.signal.aborted)
                    setFormError(requestErrorMessage(error));
            })
            .finally(() => {
                if (!controller.signal.aborted) setDetailsLoading(false);
            });

        return () => controller.abort();
    }, [dialog, request, selectedId]);

    const clearFilters = useCallback(() => {
        setRecordId('');
        setBillingName('');
        setTypeFilter('');
        setSingleDate('');
        setDateFrom('');
        setDateTo('');
        setPage(1);
    }, []);

    function openCreate() {
        // Prefer a configured bill item so the form starts with a usable price.
        const today = new Date();
        const billingDate = [
            today.getFullYear(),
            String(today.getMonth() + 1).padStart(2, '0'),
            String(today.getDate()).padStart(2, '0'),
        ].join('-');
        setForm({
            ...emptyForm,
            billingDate,
            branchId: currentBranch?.id ? String(currentBranch.id) : '',
        });
        setSelectedBillingItem(null);
        setBillingItemSearch('');
        setBillingItemListOpen(false);
        setFormError('');
        setDialog('create');
    }

    function openDetails(record: BillingRecord) {
        setSelectedId(record.id);
        setSelectedRecord(record);
        setFormError('');
        setDialog('details');
    }

    /** Open the independent status editor from the register action column. */
    function openStatusEditor(record: BillingRecord) {
        setSelectedId(record.id);
        setSelectedRecord(record);
        setFormError('');
        setDialog('status');
    }

    async function submitBill(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setSaving(true);
        setFormError('');

        try {
            const payload = {
                billing_item_id: Number(form.billingItemId),
                ...(selectedItem?.is_other
                    ? { billing_name: form.billName.trim() }
                    : {}),
                price_version_id: selectedItem?.is_other
                    ? null
                    : selectedPrice?.id,
                payee_name: currentUser.name,
                branch_id: currentBranch?.id ?? null,
                quantity: form.quantity,
                description: form.description.trim() || null,
                amount: selectedItem?.is_other
                    ? form.amount
                    : selectedPrice?.amount,
                billing_date: form.billingDate,
                due_date: form.dueDate || null,
                status: form.status,
                notes: form.notes.trim() || null,
                ...(form.cashPayment
                    ? {
                          cash_payment: {
                              amount: form.cashPaymentAmount,
                              financial_account_id: Number(form.cashAccountId),
                          },
                      }
                    : {}),
            };
            const { data } = await request<ApiEnvelope<BillingRecord>>(
                '/institute-bills',
                {
                    method: 'POST',
                    data: payload,
                },
            );

            setDialog(null);
            const paymentMessage = form.cashPayment
                ? ` A cash payment of ${money(form.cashPaymentAmount)} was recorded.`
                : '';
            setNotice(
                `${data.data.invoice_number ?? data.data.id} was created as ${data.data.approval_status}.${paymentMessage}`,
            );
            clearFilters();
            setRefreshKey((current) => current + 1);
        } catch (error) {
            setFormError(requestErrorMessage(error));
        } finally {
            setSaving(false);
        }
    }

    /** Send a bill approval-state change through the Finance status endpoint. */
    async function updateBillStatus(
        status: ApprovalStatus,
        cancellationReason: string,
    ) {
        if (selectedId === null) return;

        setStatusSaving(true);
        setFormError('');
        try {
            const { data } = await request<ApiEnvelope<BillingRecord>>(
                `/institute-bills/${selectedId}/status`,
                {
                    method: 'PATCH',
                    data: {
                        status,
                        ...(status === 'cancelled'
                            ? {
                                  cancellation_reason:
                                      cancellationReason.trim() || null,
                              }
                            : {}),
                    },
                },
            );

            setSelectedRecord(data.data);
            setNotice(
                `${data.data.invoice_number ?? data.data.id} status updated to ${data.data.approval_status}.`,
            );
            setDialog(null);
            setRefreshKey((current) => current + 1);
        } catch (error) {
            setFormError(requestErrorMessage(error));
        } finally {
            setStatusSaving(false);
        }
    }

    /** Record cash against an approved bill through the Finance API. */
    async function recordBillCashPayment(
        amount: string,
        financialAccountId: string,
    ) {
        if (selectedId === null) return;

        setPaymentSaving(true);
        setFormError('');
        try {
            const { data } = await request<ApiEnvelope<BillingRecord>>(
                `/institute-bills/${selectedId}/payments`,
                {
                    method: 'POST',
                    data: {
                        amount,
                        financial_account_id: Number(financialAccountId),
                    },
                },
            );

            setSelectedRecord(data.data);
            setNotice(
                `Cash payment of ${money(amount)} recorded. Remaining balance: ${money(data.data.balance)}.`,
            );
            setDialog(null);
            setRefreshKey((current) => current + 1);
        } catch (error) {
            setFormError(requestErrorMessage(error));
        } finally {
            setPaymentSaving(false);
        }
    }

    function printRecord() {
        document.body.dataset.billingPrint = 'record';
        window.addEventListener(
            'afterprint',
            () => delete document.body.dataset.billingPrint,
            { once: true },
        );
        window.print();
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Billings', href: '/billings/student-bill' },
                {
                    title: 'Institute billing',
                    href: '/billings/institute-billing',
                },
            ]}
        >
            <Head title="Institute billing" />
            <style>{`
                @media print {
                    body * { visibility: hidden !important; }
                    body[data-billing-print="record"] > #app { display: none !important; }
                    body[data-billing-print="record"] .billing-print-record-document,
                    body[data-billing-print="record"] .billing-print-record-document * { visibility: visible !important; }
                    body[data-billing-print="record"] .billing-print-record-document { display: flex !important; flex-direction: column !important; position: absolute !important; inset: 0 auto auto 0 !important; box-sizing: border-box !important; width: 100% !important; height: 281mm !important; max-height: 281mm !important; overflow: hidden !important; padding: 0 !important; color: #111827 !important; background: white !important; font-size: 9pt !important; }
                    .billing-print-record-document > header { padding-bottom: 3mm !important; }
                    .billing-print-record-document > section { margin-top: 4mm !important; }
                    .billing-print-record-document > section:first-of-type { margin-top: 4mm !important; }
                    .billing-print-record-document table { page-break-inside: avoid; }
                    .billing-print-record-document th, .billing-print-record-document td { padding: 1.5mm 2mm !important; }
                    .billing-print-record-document tr, .billing-print-record-document section, .billing-print-record-document header, .billing-print-record-document footer { page-break-inside: avoid; break-inside: avoid; }
                    .billing-print-record-document .billing-print-description { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; }
                    .billing-print-record-document .billing-print-notes p { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 3; overflow: hidden; }
                    .billing-print-record-document .billing-print-signature { margin-top: auto !important; padding-top: 6mm !important; }
                    .billing-print-record-document > footer { margin-top: 4mm !important; padding-top: 2mm !important; }
                    .no-print { display: none !important; }
                    @page { size: A4 portrait; margin: 8mm; }
                }
            `}</style>

            <main className="min-h-screen bg-[#F7F9FC] px-4 py-6 text-slate-800 sm:px-7 lg:px-10">
                <div className="mx-auto max-w-7xl space-y-6">
                    <header className="flex flex-wrap items-center justify-between gap-5 rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-6 text-white sm:p-8">
                        <div>
                            <p className="flex items-center gap-2 text-xs font-semibold tracking-[0.16em] text-cyan-100 uppercase">
                                <ReceiptText size={16} /> Billings
                            </p>
                            <h1 className="mt-3 text-3xl font-semibold tracking-tight">
                                Institute billing
                            </h1>
                            <p className="mt-2 max-w-2xl text-sm text-cyan-50/85">
                                Find institute bills, review line items, and
                                print billing records.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={openCreate}
                            disabled={optionsLoading}
                            className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-[#17375C] hover:bg-cyan-50 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <Plus size={17} /> Create new bill
                        </button>
                    </header>

                    {notice && (
                        <div
                            role="status"
                            className="flex items-start justify-between gap-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
                        >
                            <p>{notice}</p>
                            <button
                                type="button"
                                onClick={() => setNotice('')}
                                aria-label="Dismiss notice"
                            >
                                <X size={17} />
                            </button>
                        </div>
                    )}
                    {requestError && (
                        <div
                            role="alert"
                            className="flex items-start justify-between gap-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
                        >
                            <p>{requestError}</p>
                            <button
                                type="button"
                                onClick={() =>
                                    setRefreshKey((current) => current + 1)
                                }
                                className="font-semibold underline"
                            >
                                Retry
                            </button>
                        </div>
                    )}

                    <section
                        aria-label="Billing record filters"
                        className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"
                    >
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="font-semibold text-[#17375C]">
                                    Find billing records
                                </h2>
                                <p className="mt-1 text-xs text-slate-500">
                                    Filter by invoice number / database ID,
                                    billing name, type, or billing date.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={clearFilters}
                                className="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                            >
                                <FilterX size={15} /> Clear filters
                            </button>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <label className="text-xs font-medium text-slate-600">
                                Invoice number or database ID
                                <div className="relative">
                                    <Search
                                        size={16}
                                        className="absolute top-3.5 left-3 text-slate-400"
                                    />
                                    <input
                                        value={recordId}
                                        onChange={(event) => {
                                            setRecordId(event.target.value);
                                            setPage(1);
                                        }}
                                        placeholder="e.g. IB-2026-..."
                                        className={`${inputClass} pl-9`}
                                    />
                                </div>
                            </label>
                            <label className="text-xs font-medium text-slate-600">
                                Billing name or payee
                                <input
                                    value={billingName}
                                    onChange={(event) => {
                                        setBillingName(event.target.value);
                                        setPage(1);
                                    }}
                                    placeholder="Search bill or supplier"
                                    className={inputClass}
                                />
                            </label>
                            <label className="text-xs font-medium text-slate-600">
                                Billing type
                                <select
                                    value={typeFilter}
                                    onChange={(event) => {
                                        setTypeFilter(event.target.value);
                                        setPage(1);
                                    }}
                                    className={inputClass}
                                >
                                    <option value="">All types</option>
                                    {typeOptions.map((type) => (
                                        <option key={type.id} value={type.name}>
                                            {type.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="text-xs font-medium text-slate-600">
                                Date filter
                                <select
                                    value={dateMode}
                                    onChange={(event) =>
                                        setDateMode(
                                            event.target
                                                .value as DateFilterMode,
                                        )
                                    }
                                    className={inputClass}
                                >
                                    <option value="single">
                                        Single billing date
                                    </option>
                                    <option value="range">
                                        Billing date range
                                    </option>
                                </select>
                            </label>
                            {dateMode === 'single' ? (
                                <label className="text-xs font-medium text-slate-600">
                                    Billing date
                                    <span className="relative block">
                                        <CalendarDays
                                            size={16}
                                            className="pointer-events-none absolute top-3.5 left-3 text-slate-400"
                                        />
                                        <input
                                            type="date"
                                            value={singleDate}
                                            onChange={(event) => {
                                                setSingleDate(
                                                    event.target.value,
                                                );
                                                setPage(1);
                                            }}
                                            className={`${inputClass} pl-9`}
                                        />
                                    </span>
                                </label>
                            ) : (
                                <>
                                    <label className="text-xs font-medium text-slate-600">
                                        Billing date from
                                        <input
                                            type="date"
                                            value={dateFrom}
                                            onChange={(event) => {
                                                setDateFrom(event.target.value);
                                                setPage(1);
                                            }}
                                            className={inputClass}
                                        />
                                    </label>
                                    <label className="text-xs font-medium text-slate-600">
                                        Billing date to
                                        <input
                                            type="date"
                                            min={dateFrom || undefined}
                                            value={dateTo}
                                            onChange={(event) => {
                                                setDateTo(event.target.value);
                                                setPage(1);
                                            }}
                                            className={inputClass}
                                        />
                                    </label>
                                </>
                            )}
                        </div>
                    </section>

                    <section
                        className="overflow-hidden rounded-2xl border border-slate-200 bg-white"
                        aria-labelledby="billing-register-title"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
                            <div>
                                <h2
                                    id="billing-register-title"
                                    className="font-semibold text-[#17375C]"
                                >
                                    Institute billing register
                                </h2>
                                <p className="mt-1 text-xs font-medium text-teal-800">
                                    {isSuperUser
                                        ? 'Institute-wide access · all branches'
                                        : `Branch access · ${currentBranch?.name ?? 'assigned branch'}`}
                                </p>
                                <p className="mt-1 text-xs text-slate-500">
                                    {totalRecords} matching records · Amounts in
                                    BDT
                                </p>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-slate-50 text-xs text-slate-500">
                                    <tr>
                                        {[
                                            'Record / billing name',
                                            'Payee / branch',
                                            'Billing date',
                                            'Amount',
                                            'Due',
                                            'Payment status',
                                            'Approval status',
                                            'Actions',
                                            'View',
                                        ].map((heading) => (
                                            <th
                                                key={heading || 'actions'}
                                                className="px-4 py-3 font-medium whitespace-nowrap first:pl-5 last:pr-5"
                                            >
                                                {heading}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {loading && (
                                        <tr>
                                            <td
                                                colSpan={9}
                                                className="px-6 py-14 text-center text-sm text-slate-500"
                                            >
                                                <LoaderCircle className="mx-auto size-5 animate-spin" />
                                                <span className="mt-2 block">
                                                    Loading billing records…
                                                </span>
                                            </td>
                                        </tr>
                                    )}
                                    {!loading &&
                                        !requestError &&
                                        records.map((record) => (
                                            <tr key={record.id}>
                                                <td className="min-w-56 px-4 py-4 first:pl-5">
                                                    <p className="font-mono text-xs font-semibold text-[#17375C]">
                                                        {record.invoice_number ??
                                                            `#${record.id}`}
                                                    </p>
                                                    <p className="mt-1 font-medium text-slate-800">
                                                        {record.billing_name}
                                                    </p>
                                                </td>
                                                <td className="min-w-48 px-4 py-4">
                                                    <p className="text-xs font-medium text-slate-700">
                                                        {record.payee_name}
                                                    </p>
                                                    <p className="mt-1 text-xs text-slate-500">
                                                        {record.branch?.name ??
                                                            'Institute wide'}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-4 text-xs whitespace-nowrap text-slate-600">
                                                    {record.billing_date}
                                                </td>
                                                <td className="px-4 py-4 text-xs font-medium whitespace-nowrap">
                                                    {money(record.amount)}
                                                </td>
                                                <td className="px-4 py-4 text-xs font-semibold whitespace-nowrap text-amber-700">
                                                    {money(record.balance)}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <span
                                                        className={`rounded-full px-2.5 py-1 text-xs font-medium ${record.payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700' : record.payment_status === 'partial' ? 'bg-sky-50 text-sky-700' : 'bg-amber-50 text-amber-700'}`}
                                                    >
                                                        {record.payment_status}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <span
                                                        className={`rounded-full px-2.5 py-1 text-xs font-medium ${record.approval_status === 'approved' ? 'bg-emerald-50 text-emerald-700' : record.approval_status === 'cancelled' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-700'}`}
                                                    >
                                                        {record.approval_status}
                                                    </span>
                                                </td>
                                                <td className="no-print px-4 py-4">
                                                    <div className="flex items-center gap-2">
                                                        {(currentUser.is_super_admin ===
                                                            true ||
                                                            record.created_by_id ===
                                                                currentUser.id) && (
                                                            <>
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        openStatusEditor(
                                                                            record,
                                                                        )
                                                                    }
                                                                    aria-label={`Update approval status for ${record.invoice_number ?? record.id}`}
                                                                    title="Update approval status"
                                                                    className="rounded-lg border border-teal-200 p-2 text-teal-800 hover:bg-teal-50"
                                                                >
                                                                    <Pencil
                                                                        size={
                                                                            16
                                                                        }
                                                                    />
                                                                </button>
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="no-print px-4 py-4 last:pr-5">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            openDetails(record)
                                                        }
                                                        aria-label={`View billing record ${record.invoice_number ?? record.id}`}
                                                        title="View details and print"
                                                        className="rounded-lg border border-slate-200 p-2 text-slate-600 hover:bg-slate-50 hover:text-[#17375C]"
                                                    >
                                                        <Eye size={16} />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    {!loading &&
                                        !requestError &&
                                        records.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={9}
                                                    className="px-6 py-14 text-center"
                                                >
                                                    <FileText className="mx-auto size-8 text-slate-300" />
                                                    <p className="mt-3 text-sm font-medium text-slate-600">
                                                        No billing records found
                                                    </p>
                                                    <p className="mt-1 text-xs text-slate-400">
                                                        Try changing or clearing
                                                        your filters.
                                                    </p>
                                                </td>
                                            </tr>
                                        )}
                                </tbody>
                            </table>
                        </div>
                        <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-xs text-slate-600">
                            <span>
                                Page {page} of {lastPage}
                            </span>
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    disabled={page <= 1 || loading}
                                    onClick={() =>
                                        setPage((current) =>
                                            Math.max(1, current - 1),
                                        )
                                    }
                                    className="rounded-lg border border-slate-200 px-3 py-2 disabled:opacity-50"
                                >
                                    Previous
                                </button>
                                <button
                                    type="button"
                                    disabled={page >= lastPage || loading}
                                    onClick={() =>
                                        setPage((current) =>
                                            Math.min(lastPage, current + 1),
                                        )
                                    }
                                    className="rounded-lg border border-slate-200 px-3 py-2 disabled:opacity-50"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </main>

            {typeof document !== 'undefined' &&
                createPortal(
                    <PrintableBill
                        record={selectedRecord}
                        branding={branding}
                    />,
                    document.body,
                )}

            <Dialog
                open={dialog !== null}
                onOpenChange={(open) => {
                    if (!open) setDialog(null);
                }}
            >
                <DialogContent
                    className={`max-h-[90svh] overflow-y-auto ${dialog === 'details' ? 'sm:max-w-2xl' : 'sm:max-w-xl'}`}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {dialog === 'create'
                                ? 'Create a billing record'
                                : dialog === 'status'
                                  ? 'Update bill status'
                                  : dialog === 'payment'
                                    ? 'Record cash payment'
                                    : 'Billing record details'}
                        </DialogTitle>
                        <DialogDescription>
                            {dialog === 'create'
                                ? 'Enter bill information and choose its status.'
                                : dialog === 'status'
                                  ? 'Change the approval status for this bill.'
                                  : dialog === 'payment'
                                    ? 'Record a cash payment against this approved bill.'
                                    : 'Review the billing details and print a copy.'}
                        </DialogDescription>
                    </DialogHeader>
                    {dialog === 'details' && (
                        <>
                            {detailsLoading && (
                                <p className="py-8 text-center text-sm text-slate-500">
                                    <LoaderCircle className="mx-auto mb-2 size-5 animate-spin" />
                                    Loading bill details…
                                </p>
                            )}
                            {formError && (
                                <p
                                    role="alert"
                                    className="text-sm text-rose-700"
                                >
                                    {formError}
                                </p>
                            )}
                            {selectedRecord && (
                                <BillDetails
                                    record={selectedRecord}
                                    onClose={() => setDialog(null)}
                                    onPrint={printRecord}
                                />
                            )}
                        </>
                    )}
                    {dialog === 'status' && selectedRecord && (
                        <BillStatusEditor
                            record={selectedRecord}
                            saving={statusSaving}
                            error={formError}
                            onSave={updateBillStatus}
                            onCancel={() => setDialog(null)}
                        />
                    )}
                    {dialog === 'payment' && selectedRecord && (
                        <BillCashPaymentEditor
                            record={selectedRecord}
                            accounts={selectedRecordCashAccounts}
                            saving={paymentSaving}
                            error={formError}
                            onSave={recordBillCashPayment}
                            onCancel={() => setDialog(null)}
                        />
                    )}
                    {dialog === 'create' && (
                        <form onSubmit={submitBill} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                {selectedItem?.is_other && (
                                    <label className="text-xs font-medium text-slate-600">
                                        Bill name
                                        <input
                                            required
                                            maxLength={255}
                                            value={form.billName}
                                            onChange={(event) =>
                                                setForm({
                                                    ...form,
                                                    billName:
                                                        event.target.value,
                                                })
                                            }
                                            className={inputClass}
                                            placeholder="Enter the bill name"
                                        />
                                    </label>
                                )}
                                {selectedItem && (
                                    <div className="rounded-2xl border border-teal-200 bg-teal-50/70 p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-[11px] font-semibold tracking-wide text-teal-700 uppercase">
                                                    Selected billing item
                                                </p>
                                                <p className="mt-1 font-semibold text-[#17375C]">
                                                    {selectedItem.name}
                                                </p>
                                                {!selectedItem.is_other && (
                                                    <p className="mt-1 text-xs text-teal-800">
                                                        Configured price:{' '}
                                                        {selectedPrice
                                                            ? money(
                                                                  selectedPrice.amount,
                                                              )
                                                            : 'No active price'}
                                                    </p>
                                                )}
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setSelectedBillingItem(
                                                        null,
                                                    );
                                                    setBillingItemSearch('');
                                                    setBillingItemListOpen(
                                                        true,
                                                    );
                                                    setForm({
                                                        ...form,
                                                        billingItemId: '',
                                                        priceVersionId: '',
                                                        branchId:
                                                            currentBranch?.id
                                                                ? String(
                                                                      currentBranch.id,
                                                                  )
                                                                : '',
                                                    });
                                                }}
                                                className="shrink-0 rounded-lg border border-teal-200 bg-white px-3 py-1.5 text-xs font-semibold text-teal-800 hover:bg-teal-100"
                                            >
                                                Change item
                                            </button>
                                        </div>
                                    </div>
                                )}
                                {!selectedItem && (
                                    <label className="text-xs font-medium text-slate-600">
                                        Billing item
                                        <input
                                            required
                                            value={billingItemSearch}
                                            onFocus={() =>
                                                setBillingItemListOpen(true)
                                            }
                                            onChange={(event) => {
                                                setBillingItemSearch(
                                                    event.target.value,
                                                );
                                                setSelectedBillingItem(null);
                                                setBillingItemListOpen(true);
                                                setForm({
                                                    ...form,
                                                    billingItemId: '',
                                                    priceVersionId: '',
                                                    branchId: currentBranch?.id
                                                        ? String(
                                                              currentBranch.id,
                                                          )
                                                        : '',
                                                });
                                            }}
                                            className={inputClass}
                                            placeholder="Search billing items…"
                                            autoComplete="off"
                                        />
                                        {billingItemListOpen &&
                                        billingItemsLoading ? (
                                            <p className="mt-2 text-xs text-slate-500">
                                                Searching billing items…
                                            </p>
                                        ) : billingItemListOpen &&
                                          billingItems.length > 0 ? (
                                            <div className="mt-2 max-h-44 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-sm">
                                                {billingItems.map((item) => (
                                                    <button
                                                        key={item.id}
                                                        type="button"
                                                        onClick={() => {
                                                            setSelectedBillingItem(
                                                                item,
                                                            );
                                                            setBillingItemSearch(
                                                                item.name,
                                                            );
                                                            setBillingItemListOpen(
                                                                false,
                                                            );
                                                            setForm({
                                                                ...form,
                                                                billingItemId:
                                                                    String(
                                                                        item.id,
                                                                    ),
                                                                priceVersionId:
                                                                    '',
                                                                branchId:
                                                                    currentBranch?.id
                                                                        ? String(
                                                                              currentBranch.id,
                                                                          )
                                                                        : '',
                                                            });
                                                        }}
                                                        className={`block w-full border-b border-slate-100 px-3 py-2.5 text-left text-sm last:border-b-0 hover:bg-teal-50 ${selectedItem?.id === item.id ? 'bg-teal-50 font-semibold text-teal-900' : 'text-slate-700'}`}
                                                    >
                                                        {item.name}
                                                    </button>
                                                ))}
                                            </div>
                                        ) : billingItemListOpen ? (
                                            <p className="mt-2 text-xs text-slate-500">
                                                No matching billing items.
                                            </p>
                                        ) : null}
                                    </label>
                                )}
                                <ReadOnlyContextField
                                    label="Payer / supplier"
                                    value={currentUser.name}
                                />
                                <ReadOnlyContextField
                                    label={
                                        isSuperUser
                                            ? 'Billing scope'
                                            : 'User branch'
                                    }
                                    value={
                                        isSuperUser
                                            ? 'Institute-wide'
                                            : (currentBranch?.name ??
                                              'No branch assigned')
                                    }
                                />
                                <label className="text-xs font-medium text-slate-600">
                                    Billing date
                                    <input
                                        required
                                        type="date"
                                        value={form.billingDate}
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                billingDate: event.target.value,
                                            })
                                        }
                                        className={inputClass}
                                    />
                                </label>
                                <label className="text-xs font-medium text-slate-600">
                                    Quantity{' '}
                                    {selectedPrice?.unit_name
                                        ? `(${selectedPrice.unit_name})`
                                        : ''}
                                    <input
                                        required
                                        type="number"
                                        min="1"
                                        max="999999999"
                                        step="1"
                                        inputMode="numeric"
                                        value={form.quantity}
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                quantity: event.target.value,
                                            })
                                        }
                                        className={inputClass}
                                    />
                                </label>
                                <label className="text-xs font-medium text-slate-600">
                                    Due date{' '}
                                    <span className="font-normal text-slate-400">
                                        (optional)
                                    </span>
                                    <input
                                        type="date"
                                        min={form.billingDate || undefined}
                                        value={form.dueDate}
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                dueDate: event.target.value,
                                            })
                                        }
                                        className={inputClass}
                                    />
                                </label>
                                <label className="text-xs font-medium text-slate-600">
                                    Bill status
                                    <select
                                        value={form.status}
                                        onChange={(event) => {
                                            const status = event.target
                                                .value as BillForm['status'];
                                            setForm({
                                                ...form,
                                                status,
                                                ...(status !== 'approved'
                                                    ? {
                                                          cashPayment: false,
                                                          cashPaymentAmount: '',
                                                          cashAccountId: '',
                                                      }
                                                    : {}),
                                            });
                                        }}
                                        className={inputClass}
                                    >
                                        <option value="pending">
                                            Pending approval
                                        </option>
                                        <option value="approved">
                                            Approved
                                        </option>
                                        <option value="cancelled">
                                            Cancelled
                                        </option>
                                    </select>
                                </label>
                                {selectedItem?.is_other ? (
                                    <label className="text-xs font-medium text-slate-600 sm:col-span-2">
                                        Custom unit amount (BDT)
                                        <input
                                            required
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            value={form.amount}
                                            onChange={(event) =>
                                                setForm({
                                                    ...form,
                                                    amount: event.target.value,
                                                })
                                            }
                                            className={inputClass}
                                            placeholder="0.00"
                                        />
                                    </label>
                                ) : null}
                                <label className="text-xs font-medium text-slate-600 sm:col-span-2">
                                    Bill description{' '}
                                    <span className="font-normal text-slate-400">
                                        (optional)
                                    </span>
                                    <input
                                        value={form.description}
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                description: event.target.value,
                                            })
                                        }
                                        className={inputClass}
                                        placeholder="What is this bill for?"
                                    />
                                </label>
                                <section className="space-y-3 rounded-xl border border-slate-200 p-4 sm:col-span-2">
                                    <label className="flex items-start gap-3 text-sm font-medium text-slate-700">
                                        <input
                                            type="checkbox"
                                            checked={form.cashPayment}
                                            onChange={(event) =>
                                                setForm({
                                                    ...form,
                                                    cashPayment:
                                                        event.target.checked,
                                                    cashAccountId: event.target
                                                        .checked
                                                        ? form.cashAccountId ||
                                                          String(
                                                              cashAccountOptions[0]
                                                                  ?.id ?? '',
                                                          )
                                                        : '',
                                                })
                                            }
                                            disabled={
                                                form.status !== 'approved'
                                            }
                                            className="mt-0.5 size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-600"
                                        />
                                        <span>
                                            Record cash payment with this bill
                                            <span className="mt-1 block text-xs font-normal text-slate-500">
                                                Cash is recorded as received and
                                                confirmed. Partial payments are
                                                allowed.
                                            </span>
                                            {form.status !== 'approved' && (
                                                <span className="mt-1 block text-xs font-normal text-amber-700">
                                                    Approve the bill before
                                                    recording a payment.
                                                </span>
                                            )}
                                        </span>
                                    </label>
                                    {form.cashPayment && (
                                        <div className="grid gap-4">
                                            <label className="text-xs font-medium text-slate-600">
                                                Cash amount (BDT)
                                                <span className="mt-1 block text-xs font-normal text-slate-500">
                                                    Bill total:{' '}
                                                    {money(billTotalAmount)}
                                                </span>
                                                <input
                                                    required
                                                    type="number"
                                                    min="0.01"
                                                    max={
                                                        billTotalAmount > 0
                                                            ? billTotalAmount.toFixed(
                                                                  2,
                                                              )
                                                            : undefined
                                                    }
                                                    step="0.01"
                                                    inputMode="decimal"
                                                    value={
                                                        form.cashPaymentAmount
                                                    }
                                                    onChange={(event) =>
                                                        setForm({
                                                            ...form,
                                                            cashPaymentAmount:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                    className={inputClass}
                                                    placeholder="0.00"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setForm({
                                                            ...form,
                                                            cashPaymentAmount:
                                                                billTotalAmount.toFixed(
                                                                    2,
                                                                ),
                                                        })
                                                    }
                                                    disabled={
                                                        billTotalAmount <= 0
                                                    }
                                                    className="mt-1 text-xs font-semibold text-teal-800 hover:underline disabled:cursor-not-allowed disabled:text-slate-400"
                                                >
                                                    Use full bill amount
                                                </button>
                                            </label>
                                            <label className="text-xs font-medium text-slate-600">
                                                Cash account
                                                <select
                                                    required
                                                    value={form.cashAccountId}
                                                    onChange={(event) =>
                                                        setForm({
                                                            ...form,
                                                            cashAccountId:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                    className={inputClass}
                                                >
                                                    <option value="">
                                                        Select cash account
                                                    </option>
                                                    {cashAccountOptions.map(
                                                        (account) => (
                                                            <option
                                                                key={account.id}
                                                                value={
                                                                    account.id
                                                                }
                                                            >
                                                                {account.name}
                                                                {account.branch_id
                                                                    ? ` · ${account.code}`
                                                                    : ' · Institute-wide'}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                                {cashAccountOptions.length ===
                                                    0 && (
                                                    <span className="mt-1 block text-xs font-normal text-rose-700">
                                                        No active cash account
                                                        is configured for this
                                                        institute and branch.
                                                    </span>
                                                )}
                                            </label>
                                        </div>
                                    )}
                                </section>
                                <label className="text-xs font-medium text-slate-600 sm:col-span-2">
                                    Notes{' '}
                                    <span className="font-normal text-slate-400">
                                        (optional)
                                    </span>
                                    <textarea
                                        rows={3}
                                        value={form.notes}
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                notes: event.target.value,
                                            })
                                        }
                                        className={`${inputClass} h-auto py-3`}
                                    />
                                </label>
                            </div>
                            {formError && (
                                <p
                                    role="alert"
                                    className="text-sm text-rose-700"
                                >
                                    {formError}
                                </p>
                            )}
                            <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
                                <button
                                    type="button"
                                    onClick={() => setDialog(null)}
                                    className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-600"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={
                                        saving ||
                                        optionsLoading ||
                                        !selectedItem ||
                                        (form.cashPayment &&
                                            (!form.cashPaymentAmount ||
                                                !form.cashAccountId))
                                    }
                                    className={primaryButton}
                                >
                                    {saving ? (
                                        <LoaderCircle
                                            size={16}
                                            className="animate-spin"
                                        />
                                    ) : (
                                        <Plus size={16} />
                                    )}
                                    {saving ? 'Saving…' : 'Create bill'}
                                </button>
                            </div>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

/** Edit a bill's approval state in a dedicated dialog separate from bill details. */
function BillStatusEditor({
    record,
    saving,
    error,
    onSave,
    onCancel,
}: {
    record: BillingRecord;
    saving: boolean;
    error: string;
    onSave: (
        status: ApprovalStatus,
        cancellationReason: string,
    ) => Promise<void>;
    onCancel: () => void;
}) {
    const [nextStatus, setNextStatus] = useState<ApprovalStatus>(
        record.approval_status as ApprovalStatus,
    );
    const [cancellationReason, setCancellationReason] = useState('');

    return (
        <div className="space-y-4">
            <div className="rounded-xl bg-slate-50 p-4">
                <p className="font-mono text-xs font-semibold text-teal-700">
                    {record.invoice_number ?? `#${record.id}`}
                </p>
                <p className="mt-1 font-semibold text-[#17375C]">
                    {record.billing_name}
                </p>
                <p className="mt-1 text-xs text-slate-500">
                    Current approval status: {record.approval_status}
                </p>
            </div>
            <label className="block text-xs font-medium text-slate-600">
                New approval status
                <select
                    value={nextStatus}
                    onChange={(event) =>
                        setNextStatus(event.target.value as ApprovalStatus)
                    }
                    className={inputClass}
                    disabled={saving}
                >
                    {record.approval_status === 'draft' && (
                        <option value="draft">Draft (legacy)</option>
                    )}
                    <option value="pending">Pending approval</option>
                    <option value="approved">Approved</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </label>
            {nextStatus === 'cancelled' && (
                <label className="block text-xs font-medium text-slate-600">
                    Cancellation reason (optional)
                    <textarea
                        value={cancellationReason}
                        onChange={(event) =>
                            setCancellationReason(event.target.value)
                        }
                        rows={3}
                        className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/10"
                        disabled={saving}
                    />
                </label>
            )}
            {error && (
                <p role="alert" className="text-sm text-rose-700">
                    {error}
                </p>
            )}
            <footer className="flex justify-end gap-2 border-t border-slate-100 pt-4">
                <button
                    type="button"
                    onClick={onCancel}
                    disabled={saving}
                    className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-600 disabled:opacity-60"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    onClick={() => void onSave(nextStatus, cancellationReason)}
                    disabled={saving || nextStatus === record.approval_status}
                    className={primaryButton}
                >
                    {saving ? (
                        <LoaderCircle size={16} className="animate-spin" />
                    ) : (
                        <Save size={16} />
                    )}
                    {saving ? 'Updating…' : 'Save status'}
                </button>
            </footer>
        </div>
    );
}

/** Record an additional cash payment after the bill has been approved. */
function BillCashPaymentEditor({
    record,
    accounts,
    saving,
    error,
    onSave,
    onCancel,
}: {
    record: BillingRecord;
    accounts: CashAccountOption[];
    saving: boolean;
    error: string;
    onSave: (amount: string, accountId: string) => Promise<void>;
    onCancel: () => void;
}) {
    const [amount, setAmount] = useState(record.balance);
    const [accountId, setAccountId] = useState(String(accounts[0]?.id ?? ''));

    useEffect(() => {
        if (!accounts.some((account) => String(account.id) === accountId)) {
            setAccountId(String(accounts[0]?.id ?? ''));
        }
    }, [accountId, accounts]);

    return (
        <div className="space-y-4">
            <div className="rounded-xl bg-slate-50 p-4">
                <p className="font-mono text-xs font-semibold text-teal-700">
                    {record.invoice_number ?? `#${record.id}`}
                </p>
                <p className="mt-1 font-semibold text-[#17375C]">
                    {record.billing_name}
                </p>
                <dl className="mt-3 grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <dt className="text-slate-500">Bill total</dt>
                        <dd className="mt-1 font-semibold">
                            {money(record.amount)}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-slate-500">Remaining balance</dt>
                        <dd className="mt-1 font-semibold text-amber-700">
                            {money(record.balance)}
                        </dd>
                    </div>
                </dl>
            </div>
            <label className="block text-xs font-medium text-slate-600">
                Cash account
                <select
                    required
                    value={accountId}
                    onChange={(event) => setAccountId(event.target.value)}
                    className={inputClass}
                    disabled={saving || accounts.length === 0}
                >
                    <option value="">Select cash account</option>
                    {accounts.map((account) => (
                        <option key={account.id} value={account.id}>
                            {account.name}
                        </option>
                    ))}
                </select>
                {accounts.length === 0 && (
                    <span className="mt-1 block font-normal text-rose-700">
                        No active cash account is configured for this institute
                        and branch.
                    </span>
                )}
            </label>
            <label className="block text-xs font-medium text-slate-600">
                Cash payment amount (BDT)
                <input
                    required
                    type="number"
                    min="0.01"
                    max={record.balance}
                    step="0.01"
                    inputMode="decimal"
                    value={amount}
                    onChange={(event) => setAmount(event.target.value)}
                    className={inputClass}
                    disabled={saving}
                />
                <button
                    type="button"
                    onClick={() => setAmount(record.balance)}
                    className="mt-1 text-xs font-semibold text-teal-800 hover:underline"
                    disabled={saving}
                >
                    Pay remaining balance
                </button>
            </label>
            <p className="text-xs text-slate-500">
                Cash payments are recorded as received and confirmed
                immediately.
            </p>
            {error && (
                <p role="alert" className="text-sm text-rose-700">
                    {error}
                </p>
            )}
            <footer className="flex justify-end gap-2 border-t border-slate-100 pt-4">
                <button
                    type="button"
                    onClick={onCancel}
                    disabled={saving}
                    className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-600 disabled:opacity-60"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    onClick={() => void onSave(amount, accountId)}
                    disabled={
                        saving || accounts.length === 0 || !amount || !accountId
                    }
                    className={primaryButton}
                >
                    {saving ? (
                        <LoaderCircle size={16} className="animate-spin" />
                    ) : (
                        <Banknote size={16} />
                    )}
                    {saving ? 'Recording…' : 'Record payment'}
                </button>
            </footer>
        </div>
    );
}

/** Keep the view dialog and printed invoice consistent with the API's bill snapshot. */
function BillDetails({
    record,
    onClose,
    onPrint,
}: {
    record: BillingRecord;
    onClose: () => void;
    onPrint: () => void;
}) {
    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                    <p className="font-mono text-xs font-semibold text-teal-700">
                        {record.invoice_number ?? `#${record.id}`}
                    </p>
                    <h2 className="mt-1 text-xl font-semibold text-[#17375C]">
                        {record.billing_name}
                    </h2>
                    <p className="mt-1 text-sm text-slate-500">
                        {record.type?.name}
                        {record.type?.category
                            ? ` · ${record.type.category}`
                            : ''}{' '}
                        · {record.branch?.name ?? 'Institute wide'}
                    </p>
                </div>
                <span className="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700">
                    {record.payment_status}
                </span>
            </div>
            <dl className="grid gap-4 sm:grid-cols-2">
                {[
                    ['Payee', record.payee_name],
                    ['Employee ID', record.created_by_employee_id ?? ''],
                    ['Billing date', record.billing_date],
                    ['Due date', record.due_date ?? ''],
                    ['Invoice number', record.invoice_number ?? ''],
                    ['Approval status', record.approval_status],
                ]
                    .filter(([, value]) => value !== '')
                    .map(([label, value]) => (
                        <div key={label}>
                            <dt className="text-xs text-slate-500">{label}</dt>
                            <dd className="mt-1 text-sm font-medium text-slate-800">
                                {value}
                            </dd>
                        </div>
                    ))}
            </dl>
            <div>
                <h3 className="mb-2 text-sm font-semibold text-[#17375C]">
                    Bill details
                </h3>
                <div className="overflow-hidden rounded-xl border border-slate-200">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs text-slate-500">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Billing item
                                </th>
                                <th className="px-2 py-3 text-right font-medium whitespace-nowrap">
                                    Qty / Unit
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Unit amount
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {record.items.map((line, index) => (
                                <tr key={`${line.description}-${index}`}>
                                    <td className="px-4 py-3">
                                        <span className="font-medium">
                                            {line.item_name}
                                        </span>
                                        {line.description && (
                                            <span className="mt-1 block text-xs text-slate-500">
                                                {line.description}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {quantityWithUnit(line)}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {money(line.unit_amount)}
                                    </td>
                                    <td className="px-4 py-3 text-right font-medium">
                                        {money(line.total)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="bg-slate-50">
                            <tr>
                                <td
                                    colSpan={4}
                                    className="px-4 py-3 text-right font-semibold"
                                >
                                    Bill total
                                </td>
                                <td className="px-4 py-3 text-right font-semibold text-[#17375C]">
                                    {money(record.amount)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <dl className="grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
                <div>
                    <dt className="text-xs text-slate-500">Paid</dt>
                    <dd className="mt-1 text-sm font-semibold text-emerald-700">
                        {money(record.paid_amount)}
                    </dd>
                </div>
                <div>
                    <dt className="text-xs text-slate-500">Outstanding</dt>
                    <dd className="mt-1 text-sm font-semibold text-amber-700">
                        {money(record.balance)}
                    </dd>
                </div>
            </dl>
            {record.payments.length > 0 && (
                <div>
                    <h3 className="text-xs font-semibold text-slate-600">
                        Payments
                    </h3>
                    <ul className="mt-2 space-y-2">
                        {record.payments.map((payment) => (
                            <li
                                key={payment.id}
                                className="flex flex-wrap justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2 text-xs"
                            >
                                <span>
                                    {payment.payment_number} ·{' '}
                                    {payment.payment_method
                                        ? `${payment.payment_method}${payment.financial_account ? ` · ${payment.financial_account}` : ''}`
                                        : payment.status}
                                </span>
                                <span className="font-medium">
                                    {money(payment.amount)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            {record.notes && (
                <div>
                    <h3 className="text-xs font-semibold text-slate-600">
                        Notes
                    </h3>
                    <p className="mt-1 text-sm text-slate-700">
                        {record.notes}
                    </p>
                </div>
            )}
            <div className="no-print flex justify-end gap-2 border-t border-slate-100 pt-4">
                <button
                    type="button"
                    onClick={onClose}
                    className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600"
                >
                    Close
                </button>
                <button
                    type="button"
                    onClick={onPrint}
                    className={primaryButton}
                >
                    <Printer size={16} /> Print record
                </button>
            </div>
        </div>
    );
}

/** Render a dedicated print document instead of printing the dialog layout. */
function PrintableBill({
    record,
    branding,
}: {
    record: BillingRecord | null;
    branding?: {
        phone: string | null;
        email: string | null;
        logo_url: string;
        name: string;
    };
}) {
    if (!record) return null;

    return (
        <article className="billing-print-record-document hidden px-10 py-8 text-slate-900">
            <header className="flex items-start justify-between gap-8 border-b-2 border-[#17375C] pb-5">
                <div>
                    <img
                        src={branding?.logo_url ?? '/apple-touch-icon.png'}
                        alt={`${branding?.name ?? 'CMUD'} logo`}
                        className="mb-1 h-12 max-w-48 object-contain object-left"
                    />
                    <p className="mt-1 text-xs text-slate-500">
                        CMUD {record.branch?.name ?? 'Institute-wide'} Office
                    </p>
                    {(branding?.phone || branding?.email) && (
                        <p className="mt-1 text-[10px] text-slate-500">
                            {[branding.phone, branding.email]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                    )}
                </div>
                <div className="text-right">
                    <h1 className="text-2xl font-bold tracking-wide text-[#17375C]">
                        INSTITUTE BILL
                    </h1>
                    <p className="mt-1 text-[10px] font-semibold tracking-[0.18em] text-slate-500 uppercase">
                        Billing record copy
                    </p>
                </div>
            </header>
            <section className="mt-6 grid grid-cols-2 gap-8">
                <div>
                    <h2 className="text-[10px] font-bold tracking-[0.16em] text-slate-500 uppercase">
                        Payee
                    </h2>
                    <p className="mt-2 text-base font-semibold">
                        {record.payee_name}
                    </p>
                    {record.created_by_employee_id && (
                        <p className="mt-1 text-sm text-slate-600">
                            Employee ID: {record.created_by_employee_id}
                        </p>
                    )}
                    <p className="mt-1 text-sm text-slate-600">
                        {record.branch?.name ?? 'Institute wide'}
                    </p>
                </div>
                <dl className="grid grid-cols-2 gap-x-5 gap-y-3 rounded-lg border border-slate-200 p-4">
                    <PrintField
                        label="Invoice number"
                        value={record.invoice_number ?? `#${record.id}`}
                    />
                    <PrintField
                        label="Expense type / category"
                        value={
                            record.type?.category
                                ? `${record.type.name} / ${record.type.category}`
                                : (record.type?.name ?? '—')
                        }
                    />
                    <PrintField
                        label="Billing date"
                        value={printDate(record.billing_date)}
                    />
                    <PrintField
                        label="Due date"
                        value={
                            record.due_date ? printDate(record.due_date) : '—'
                        }
                    />
                </dl>
            </section>
            <section className="mt-7">
                <div className="mb-3 flex items-end justify-between gap-4">
                    <div>
                        <h2 className="text-base font-semibold">
                            {record.billing_name}
                        </h2>
                        <p className="mt-1 text-xs text-slate-500">
                            Itemized bill details
                        </p>
                    </div>
                    <p className="text-xs text-slate-500">Currency: BDT</p>
                </div>
                <table className="w-full table-fixed border-collapse text-xs">
                    <colgroup>
                        <col className="w-[5%]" />
                        <col className="w-[42%]" />
                        <col className="w-[12%]" />
                        <col className="w-[20%]" />
                        <col className="w-[21%]" />
                    </colgroup>
                    <thead>
                        <tr className="border-y border-slate-300 bg-slate-50">
                            <th className="px-3 py-2.5 text-left font-semibold">
                                #
                            </th>
                            <th className="px-3 py-2.5 text-left font-semibold">
                                Billing item
                            </th>
                            <th className="px-3 py-2.5 text-right font-semibold">
                                Quantity / Unit
                            </th>
                            <th className="px-3 py-2.5 text-right font-semibold">
                                Unit amount
                            </th>
                            <th className="px-3 py-2.5 text-right font-semibold">
                                Line total
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {record.items.map((line, index) => (
                            <tr
                                key={`${line.description}-${index}`}
                                className="border-b border-slate-200"
                            >
                                <td className="px-3 py-3 text-slate-500">
                                    {index + 1}
                                </td>
                                <td className="px-3 py-3">
                                    <span className="font-medium">
                                        {line.item_name}
                                    </span>
                                    {line.description && (
                                        <span className="billing-print-description mt-1 block text-[10px] text-slate-500">
                                            {line.description}
                                        </span>
                                    )}
                                </td>
                                <td className="px-3 py-3 text-right tabular-nums">
                                    {quantityWithUnit(line)}
                                </td>
                                <td className="px-3 py-3 text-right tabular-nums">
                                    {money(line.unit_amount)}
                                </td>
                                <td className="px-3 py-3 text-right font-medium tabular-nums">
                                    {money(line.total)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
            <section className="mt-5 flex justify-end">
                <dl className="w-72 space-y-2 text-xs">
                    <div className="flex justify-between gap-5">
                        <dt className="text-slate-600">Bill total</dt>
                        <dd className="font-semibold tabular-nums">
                            {money(record.amount)}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-5">
                        <dt className="text-slate-600">Paid</dt>
                        <dd className="tabular-nums">
                            {money(record.paid_amount)}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-5 border-t-2 border-[#17375C] pt-2 text-sm">
                        <dt className="font-bold">Balance due</dt>
                        <dd className="font-bold tabular-nums">
                            {money(record.balance)}
                        </dd>
                    </div>
                </dl>
            </section>
            {record.notes && (
                <section className="billing-print-notes mt-7 rounded-lg border border-slate-200 p-4">
                    <h2 className="text-[10px] font-bold tracking-[0.14em] text-slate-500 uppercase">
                        Notes
                    </h2>
                    <p className="mt-2 text-xs leading-5 whitespace-pre-wrap">
                        {record.notes}
                    </p>
                </section>
            )}
            <section className="billing-print-signature mt-16 flex justify-end text-xs">
                <div className="w-64 text-center">
                    <p className="text-sm font-semibold">
                        {record.created_by_name ?? '—'}
                    </p>
                    {record.created_by_designation && (
                        <p className="mt-1 mb-8 text-xs text-slate-600">
                            {record.created_by_designation}
                        </p>
                    )}
                    {!record.created_by_designation && <div className="mb-8" />}
                    <div className="border-t border-slate-400 pt-2">
                        Issued by / Signature
                    </div>
                </div>
            </section>
            <footer className="mt-8 border-t border-slate-200 pt-3 text-center text-[10px] text-slate-500">
                Institute Billing · {record.invoice_number ?? record.id}
            </footer>
        </article>
    );
}

function PrintField({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-[10px] tracking-wide text-slate-500 uppercase">
                {label}
            </dt>
            <dd className="mt-1 text-xs font-semibold">{value}</dd>
        </div>
    );
}

/** Show identity and branch context supplied by the authenticated session. */
function ReadOnlyContextField({
    label,
    value,
}: {
    label: string;
    value: string;
}) {
    return (
        <div className="text-xs font-medium text-slate-600">
            {label}
            <p
                className={`${inputClass} flex items-center bg-slate-50 text-slate-700`}
            >
                {value}
            </p>
        </div>
    );
}

/** Format date-only values without shifting their displayed day across time zones. */
function printDate(value: string): string {
    const [year, month, day] = value.split('-').map(Number);
    return new Intl.DateTimeFormat('en-BD', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(year, month - 1, day));
}
