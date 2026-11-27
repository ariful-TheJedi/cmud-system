import AppLayout from '@/layouts/app-layout';
import { useApi } from '@/hooks/use-api';
import { Head, usePage } from '@inertiajs/react';
import { CalendarDays, Eye, FilterX, Pencil, Plus, Printer, ReceiptText, Search, Trash2, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react';
import PrintableInstituteOtherBill, { type InstituteOtherBillPrintHeader } from './components/printable-institute-other-bill';
import BillingPagination from './components/billing-pagination';

type BillItem = {
    description: string;
    quantity: number;
    unit_price: string;
    line_total: string;
};

type Bill = {
    id: number;
    bill_number: string;
    university_name: string;
    issuer_name: string;
    bill_items: BillItem[];
    total_amount: string;
    bill_date: string;
    due_on: string | null;
    status: string;
    rejection_reason: string | null;
    notes: string | null;
    recorded_by: string | null;
    created_at: string | null;
};

type BillPage = {
    data: Bill[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
};
type ItemForm = { description: string; quantity: string; unit_price: string };
type BillStatus = 'draft' | 'pending_approval' | 'approved' | 'rejected';
type BillForm = { university_name: string; issuer_name: string; bill_date: string; due_on: string; status: BillStatus; rejection_reason: string; notes: string; bill_items: ItemForm[] };

const inputClass = 'mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/10';
const emptyItem = (): ItemForm => ({ description: '', quantity: '1', unit_price: '' });
const localDate = () => { const date = new Date(); return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`; };
const emptyForm = (issuer = ''): BillForm => ({ university_name: '', issuer_name: issuer, bill_date: localDate(), due_on: '', status: 'draft', rejection_reason: '', notes: '', bill_items: [emptyItem()] });
const money = (amount: string | number) => new Intl.NumberFormat('en-BD', { style: 'currency', currency: 'BDT' }).format(Number(amount) || 0);
const escapeHtml = (value: unknown) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character] ?? character);

/** Custom Institute Other Bill registry, editor, and printable invoice. */
export default function InstituteOtherBillPage() {
    const { branding, auth } = usePage<{ branding?: { name?: string; logo_url?: string }; auth: { user: { name: string; full_name?: string | null } } }>().props;
    const { request } = useApi();
    const [rows, setRows] = useState<Bill[]>([]);
    const [meta, setMeta] = useState<BillPage['meta']>({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
    const [page, setPage] = useState(1);
    const [searchText, setSearchText] = useState('');
    const [dateText, setDateText] = useState('');
    const [filters, setFilters] = useState({ search: '', date: '' });
    const [form, setForm] = useState<BillForm>(() => emptyForm(auth.user.full_name || auth.user.name));
    const [invoiceHeader, setInvoiceHeader] = useState<InstituteOtherBillPrintHeader | null>(null);
    const [dialog, setDialog] = useState<'form' | 'view' | null>(null);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [selected, setSelected] = useState<Bill | null>(null);
    const [printJob, setPrintJob] = useState<{ bill: Bill; header: InstituteOtherBillPrintHeader } | null>(null);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');

    const total = useMemo(() => form.bill_items.reduce((sum, item) => sum + (Number(item.quantity) || 0) * (Number(item.unit_price) || 0), 0), [form.bill_items]);

    const loadBills = useCallback(async (signal: AbortSignal, requestedPage: number, applied: typeof filters) => {
        setLoading(true);
        setError('');
        try {
            const query = new URLSearchParams({ page: String(requestedPage), per_page: '15' });
            if (applied.search) query.set('search', applied.search);
            if (applied.date) query.set('date', applied.date);
            const response = await request<BillPage>(`/institute-other-bills?${query}`, { signal });
            if (signal.aborted) return;
            setRows(response.data.data);
            setMeta(response.data.meta);
        } catch (reason) {
            if (!signal.aborted) {
                setRows([]);
                setError(reason instanceof Error ? reason.message : 'Unable to load institute bills.');
            }
        } finally {
            if (!signal.aborted) setLoading(false);
        }
    }, [request]);

    useEffect(() => {
        const controller = new AbortController();
        void loadBills(controller.signal, page, filters);
        return () => controller.abort();
    }, [filters, loadBills, page]);

    useEffect(() => {
        const controller = new AbortController();
        void request<{ data: { invoice_header: InstituteOtherBillPrintHeader } }>('/institute-other-bills/options', { signal: controller.signal })
            .then((response) => { if (!controller.signal.aborted) setInvoiceHeader(response.data.data.invoice_header); })
            .catch((reason) => { if (!controller.signal.aborted) setError(reason instanceof Error ? reason.message : 'Unable to load the main branch invoice details.'); });
        return () => controller.abort();
    }, [request]);

    useEffect(() => {
        if (!printJob) return;

        const originalTitle = document.title;
        document.title = printJob.bill.bill_number;
        const finishPrint = () => {
            document.title = originalTitle;
            setPrintJob(null);
        };
        window.addEventListener('afterprint', finishPrint, { once: true });
        const frame = window.requestAnimationFrame(() => window.print());

        return () => {
            window.cancelAnimationFrame(frame);
            window.removeEventListener('afterprint', finishPrint);
            document.title = originalTitle;
        };
    }, [printJob]);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setPage(1);
        setFilters({ search: searchText.trim(), date: dateText });
    }

    function clearFilters() {
        setSearchText('');
        setDateText('');
        setPage(1);
        setFilters({ search: '', date: '' });
    }

    function openCreate() {
        setEditingId(null);
        setForm(emptyForm(auth.user.full_name || auth.user.name));
        setError('');
        setDialog('form');
    }

    function openEdit(bill: Bill) {
        setSelected(bill);
        setEditingId(bill.id);
        setForm({
            university_name: bill.university_name,
            issuer_name: bill.issuer_name || bill.recorded_by || auth.user.full_name || auth.user.name,
            bill_date: bill.bill_date,
            due_on: bill.due_on ?? '',
            status: bill.status as BillStatus,
            rejection_reason: bill.rejection_reason ?? '',
            notes: bill.notes ?? '',
            bill_items: bill.bill_items.map((item) => ({ description: item.description, quantity: String(item.quantity), unit_price: item.unit_price })),
        });
        setError('');
        setDialog('form');
    }

    async function openBill(bill: Bill) {
        setError('');
        try {
            const response = await request<{ data: Bill }>(`/institute-other-bills/${bill.id}`);
            setSelected(response.data.data);
            setDialog('view');
        } catch (reason) {
            setError(reason instanceof Error ? reason.message : 'Unable to load bill details.');
        }
    }

    function updateItem(index: number, key: keyof ItemForm, value: string) {
        setForm((current) => ({ ...current, bill_items: current.bill_items.map((item, itemIndex) => itemIndex === index ? { ...item, [key]: value } : item) }));
    }

    async function saveBill(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setSaving(true);
        setError('');
        const payload = {
            university_name: form.university_name.trim(),
            issuer_name: form.issuer_name.trim(),
            bill_date: form.bill_date,
            due_on: form.due_on || null,
            status: form.status,
            rejection_reason: form.status === 'rejected' ? form.rejection_reason.trim() || null : null,
            notes: form.notes || null,
            bill_items: form.bill_items.map((item) => ({
                description: item.description.trim(),
                quantity: Number(item.quantity),
                unit_price: item.unit_price,
            })),
        };
        try {
            await request(editingId ? `/institute-other-bills/${editingId}` : '/institute-other-bills', {
                method: editingId ? 'PUT' : 'POST',
                data: payload,
            });
            setDialog(null);
            setNotice(editingId ? 'Bill updated.' : 'Bill created.');
            setPage(1);
            setFilters((current) => ({ ...current }));
        } catch (reason) {
            setError(reason instanceof Error ? reason.message : 'Unable to save bill.');
        } finally {
            setSaving(false);
        }
    }

    async function deleteBill(bill: Bill) {
        if (!window.confirm(`Delete draft bill ${bill.bill_number}?`)) return;
        setError('');
        try {
            await request(`/institute-other-bills/${bill.id}`, { method: 'DELETE' });
            setDialog(null);
            setNotice('Bill deleted.');
            setPage(1);
            setFilters((current) => ({ ...current }));
        } catch (reason) {
            setError(reason instanceof Error ? reason.message : 'Unable to delete bill.');
        }
    }

    async function printBill(bill: Bill) {
        setError('');
        try {
            const response = await request<{ data: { invoice_header: InstituteOtherBillPrintHeader } }>(
                '/institute-other-bills/options',
            );
            const printHeader = response.data.data.invoice_header;
            if (!printHeader) throw new Error('The main branch invoice details were not returned by the server.');
            setInvoiceHeader(printHeader);
            setPrintJob({ bill, header: printHeader });
        } catch (reason) {
            const message = reason instanceof Error ? reason.message : 'Unable to prepare the printable invoice.';
            setError(message);
        }
    }

    const breadcrumbs = [{ title: 'Billings', href: '/billings/student-bill' }, { title: 'Institute Other Bill', href: '/billings/institute-other-bill' }];

    return <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Institute Other Bill" />
        <main className="min-h-screen bg-[#F7F9FC] px-4 py-6 text-slate-800 sm:px-7 lg:px-10">
        <div className="mx-auto w-full max-w-7xl space-y-6">
            <header className="flex flex-wrap items-center justify-between gap-5 rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-6 text-white sm:p-8">
                <div>
                    <p className="flex items-center gap-2 text-xs font-semibold tracking-[0.16em] text-cyan-100 uppercase"><ReceiptText size={16} />Billings</p>
                    <h1 className="mt-3 text-3xl font-semibold tracking-tight">Institute Other Bill</h1>
                    <p className="mt-2 max-w-2xl text-sm text-cyan-50/85">Create and manage custom bills for external organizations.</p>
                </div>
                <button type="button" onClick={openCreate} className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-[#17375C] hover:bg-cyan-50"><Plus size={17} />Create new bill</button>
            </header>

            <form onSubmit={applyFilters} className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div><h2 className="font-semibold text-[#17375C]">Find billing records</h2><p className="mt-1 text-xs text-slate-500">Search by bill number and filter by bill issue date.</p></div>
                    <button type="button" onClick={clearFilters} className="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100"><FilterX size={15} />Clear filters</button>
                </div>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(220px,0.7fr)_auto] lg:items-end">
                    <label className="text-xs font-medium text-slate-600">Bill number
                        <span className="relative block"><Search size={16} className="absolute top-3.5 left-3 text-slate-400" /><input value={searchText} onChange={(event) => setSearchText(event.target.value)} placeholder="Search bill ID" className={`${inputClass} pl-9`} /></span>
                    </label>
                    <label className="text-xs font-medium text-slate-600">Bill issue date
                        <span className="relative block"><CalendarDays size={16} className="pointer-events-none absolute top-3.5 left-3 text-slate-400" /><input type="date" value={dateText} onChange={(event) => setDateText(event.target.value)} className={`${inputClass} pl-9`} /></span>
                    </label>
                    <button className="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#17375C] px-5 text-sm font-semibold text-white hover:bg-[#214b78]"><Search size={16} />Search bills</button>
                </div>
            </form>

            {notice && <div role="status" className="flex items-center justify-between rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{notice}<button onClick={() => setNotice('')} aria-label="Dismiss notice"><X size={16} /></button></div>}
            {error && <p role="alert" className="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</p>}

            <section className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3"><div><h2 className="font-semibold text-slate-800">Bill registry</h2><p className="text-xs text-slate-500">{meta.total} records</p></div></div>
                <div className="overflow-x-auto"><table className="w-full min-w-[760px] text-left text-sm"><thead className="bg-slate-50 text-xs text-slate-500"><tr><th className="px-4 py-3">Bill number</th><th className="px-4 py-3">Organization</th><th className="px-4 py-3">Date</th><th className="px-4 py-3">Total</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Actions</th></tr></thead><tbody className="divide-y divide-slate-100">
                    {loading ? <tr><td colSpan={6} className="px-4 py-10 text-center text-slate-500">Loading bills…</td></tr> : rows.length === 0 ? <tr><td colSpan={6} className="px-4 py-10 text-center text-slate-500">No institute bills match these filters.</td></tr> : rows.map((bill) => <tr key={bill.id} className="hover:bg-slate-50/70"><td className="px-4 py-3 font-mono text-xs text-slate-700">{bill.bill_number}</td><td className="px-4 py-3 font-medium text-slate-800">{bill.university_name}</td><td className="px-4 py-3 text-slate-600">{bill.bill_date}</td><td className="px-4 py-3 font-semibold text-slate-800">{money(bill.total_amount)}</td><td className="px-4 py-3"><span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium capitalize text-slate-700">{bill.status.replaceAll('_', ' ')}</span></td><td className="px-4 py-3"><div className="flex justify-end gap-1"><button type="button" title="View and print" aria-label="View and print bill" onClick={() => void openBill(bill)} className="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900"><Eye size={15} /></button><button type="button" title="Update bill" aria-label="Update bill" onClick={() => openEdit(bill)} className="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-semibold text-[#17375C] hover:bg-blue-50"><Pencil size={15} /></button></div></td></tr>)}
                </tbody></table></div>
                <BillingPagination page={meta.current_page} lastPage={meta.last_page} perPage={meta.per_page} total={meta.total} loading={loading} recordLabel="institute bills" onPageChange={setPage} />
            </section>
        </div>
        </main>

        {dialog && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setDialog(null); }}>
            <section role="dialog" aria-modal="true" aria-labelledby="bill-dialog-title" className="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <header className="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-100 bg-white/95 px-5 py-4 backdrop-blur sm:px-7">
                    <div><p className="text-[10px] font-bold tracking-[0.18em] text-teal-700 uppercase">{dialog === 'view' ? 'Bill details' : 'Bill builder'}</p><h2 id="bill-dialog-title" className="mt-1 text-xl font-semibold text-slate-900">{dialog === 'view' ? selected?.bill_number : editingId ? 'Edit institute bill' : 'Create institute bill'}</h2><p className="mt-1 text-sm text-slate-500">{dialog === 'view' ? 'Review the saved bill or print it.' : 'Enter the recipient and bill items. The total updates automatically.'}</p></div>
                    <button onClick={() => setDialog(null)} aria-label="Close dialog" className="rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-50"><X size={18} /></button>
                </header>
                {dialog === 'form' ? <form onSubmit={saveBill} className="space-y-5 px-5 py-5 sm:px-7">
                    <section className="rounded-xl border border-slate-200 p-4 sm:p-5">
                        <div className="mb-4"><h3 className="text-sm font-semibold text-slate-800">Bill information</h3><p className="mt-1 text-xs text-slate-500">Choose who the bill is addressed to and when it is issued.</p></div>
                        <div className="grid gap-4 sm:grid-cols-2"><label className="text-xs font-semibold text-slate-600 sm:col-span-2">Bill Name<input required maxLength={255} value={form.university_name} onChange={(event) => setForm({ ...form, university_name: event.target.value })} className={inputClass} placeholder="Enter bill name" /></label><label className="text-xs font-semibold text-slate-600 sm:col-span-2">Issued by<input required maxLength={255} value={form.issuer_name} onChange={(event) => setForm({ ...form, issuer_name: event.target.value })} className={inputClass} placeholder="Name shown on invoice" /><span className="mt-1 block font-normal text-slate-400">Defaults to your account name; edit only when institute policy allows.</span></label><label className="text-xs font-semibold text-slate-600">Bill issue date<input required type="date" value={form.bill_date} onChange={(event) => setForm({ ...form, bill_date: event.target.value })} className={inputClass} /></label><label className="text-xs font-semibold text-slate-600">Payment due date <span className="font-normal text-slate-400">(optional)</span><input type="date" min={form.bill_date} value={form.due_on} onChange={(event) => setForm({ ...form, due_on: event.target.value })} className={inputClass} /></label></div>
                    </section>
                    <section className="rounded-xl border border-slate-200 p-4 sm:p-5">
                        <div className="mb-4"><h3 className="text-sm font-semibold text-slate-800">Approval status</h3><p className="mt-1 text-xs text-slate-500">Choose the review state for this bill.</p></div>
                        <label className="block w-full max-w-sm text-xs font-semibold text-slate-600">Status
                            <select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value as BillStatus })} className={`${inputClass} block w-full`}>
                                <option value="draft">Draft</option>
                                <option value="pending_approval">Pending approval</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </label>
                        {form.status === 'rejected' && <label className="mt-4 block text-xs font-semibold text-slate-600">Rejection reason <span className="font-normal text-slate-400">(optional)</span><textarea maxLength={2000} rows={2} value={form.rejection_reason} onChange={(event) => setForm({ ...form, rejection_reason: event.target.value })} className="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" /></label>}
                    </section>
                    <section className="rounded-xl border border-slate-200 p-4 sm:p-5">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3"><div><h3 className="text-sm font-semibold text-slate-800">Bill items</h3><p className="mt-1 text-xs text-slate-500">Add a description, quantity, and price for each item.</p></div><button type="button" onClick={() => setForm((current) => ({ ...current, bill_items: [...current.bill_items, emptyItem()] }))} className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"><Plus size={14} />Add item</button></div>
                        <div className="hidden grid-cols-[minmax(0,1fr)_100px_150px_44px] gap-3 px-3 pb-2 text-[10px] font-bold tracking-wide text-slate-400 uppercase sm:grid"><span>Item description</span><span>Quantity</span><span>Unit price</span><span /></div>
                        <div className="space-y-3">{form.bill_items.map((item, index) => <div key={index} className="grid gap-3 rounded-lg bg-slate-50 p-3 sm:grid-cols-[minmax(0,1fr)_100px_150px_44px] sm:items-end"><label className="text-[11px] font-semibold text-slate-500">Item description<input required value={item.description} onChange={(event) => updateItem(index, 'description', event.target.value)} className={inputClass} placeholder="Service or item" /></label><label className="text-[11px] font-semibold text-slate-500">Quantity<input required type="number" min="1" step="1" value={item.quantity} onChange={(event) => updateItem(index, 'quantity', event.target.value)} className={inputClass} /></label><label className="text-[11px] font-semibold text-slate-500">Unit price<input required type="number" min="0.01" step="0.01" value={item.unit_price} onChange={(event) => updateItem(index, 'unit_price', event.target.value)} className={inputClass} /></label><button type="button" aria-label={`Remove item ${index + 1}`} disabled={form.bill_items.length === 1} onClick={() => setForm((current) => ({ ...current, bill_items: current.bill_items.filter((_, itemIndex) => itemIndex !== index) }))} className="flex h-10 items-center justify-center rounded-lg text-rose-600 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-30"><Trash2 size={16} /></button><div className="text-right text-xs text-slate-500 sm:col-span-4">Line total <span className="ml-1 font-semibold text-slate-700">{money((Number(item.quantity) || 0) * (Number(item.unit_price) || 0))}</span></div></div>)}</div>
                    </section>
                    <section className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_280px] sm:items-start">
                        <label className="text-xs font-semibold text-slate-600">Notes <span className="font-normal text-slate-400">(optional)</span><textarea rows={3} value={form.notes} onChange={(event) => setForm({ ...form, notes: event.target.value })} className="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/10" placeholder="Add any additional bill details" /></label>
                        <div className="rounded-xl bg-[#17375C] p-4 text-white"><p className="text-xs font-medium text-blue-100">Bill total</p><p className="mt-1 text-2xl font-bold">{money(total)}</p><p className="mt-2 text-xs text-blue-100">{form.bill_items.length} {form.bill_items.length === 1 ? 'item' : 'items'} · Issued {form.bill_date || '—'}</p></div>
                    </section>
                    {error && <p role="alert" className="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</p>}
                    <footer className="sticky bottom-0 -mx-5 flex justify-end gap-2 border-t border-slate-100 bg-white/95 px-5 py-4 backdrop-blur sm:-mx-7 sm:px-7"><button type="button" onClick={() => setDialog(null)} className="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button disabled={saving || total <= 0} className="rounded-lg bg-[#17375C] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#214b78] disabled:cursor-not-allowed disabled:opacity-50">{saving ? 'Saving…' : editingId ? 'Save changes' : 'Create bill'}</button></footer>
                </form> : selected && <div className="space-y-5 px-5 py-5 sm:px-7"><div className="grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2"><Detail label="Bill number" value={selected.bill_number} /><Detail label="Bill name" value={selected.university_name} /><Detail label="Bill date" value={selected.bill_date} /><Detail label="Due date" value={selected.due_on || '—'} /><Detail label="Status" value={selected.status.replaceAll('_', ' ')} /><Detail label="Issued by" value={selected.issuer_name || selected.recorded_by || '—'} /><Detail label="Created by (audit)" value={selected.recorded_by || '—'} /></div><div className="overflow-x-auto rounded-lg border border-slate-200"><table className="w-full min-w-[560px] text-sm"><thead className="bg-slate-50 text-xs text-slate-500"><tr><th className="px-3 py-2 text-left">Item</th><th className="px-3 py-2 text-right">Qty</th><th className="px-3 py-2 text-right">Unit price</th><th className="px-3 py-2 text-right">Line total</th></tr></thead><tbody className="divide-y divide-slate-100">{selected.bill_items.map((item, index) => <tr key={`${item.description}-${index}`}><td className="px-3 py-3">{item.description}</td><td className="px-3 py-3 text-right">{item.quantity}</td><td className="px-3 py-3 text-right">{money(item.unit_price)}</td><td className="px-3 py-3 text-right font-medium">{money(item.line_total)}</td></tr>)}</tbody></table></div><div className="flex justify-end text-lg font-bold text-[#17375C]">Total: {money(selected.total_amount)}</div>{selected.notes && <p className="text-sm text-slate-600">{selected.notes}</p>}{error && <p role="alert" className="text-sm text-rose-700">{error}</p>}<footer className="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4"><button onClick={() => printBill(selected)} className="inline-flex items-center gap-2 rounded-lg bg-[#17375C] px-4 py-2 text-sm font-semibold text-white"><Printer size={16} />Print bill</button><button type="button" title="Update bill" aria-label="Update bill" onClick={() => openEdit(selected)} className="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-[#17375C]"><Pencil size={16} /></button>{['draft', 'pending_approval', 'rejected'].includes(selected.status) && <button onClick={() => void deleteBill(selected)} className="inline-flex items-center gap-2 rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700"><Trash2 size={16} />Delete draft</button>}</footer></div>}
            </section>
        </div>}
        <div id="institute-other-bill-print-root" aria-hidden="true">
            {printJob && <PrintableInstituteOtherBill bill={printJob.bill} header={printJob.header} />}
        </div>
    </AppLayout>;
}

function Detail({ label, value }: { label: string; value: string }) {
    return <div><p className="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{label}</p><p className="mt-1 break-words text-sm font-medium capitalize text-slate-800">{value}</p></div>;
}
