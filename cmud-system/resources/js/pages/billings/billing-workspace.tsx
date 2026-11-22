import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Banknote, CreditCard, Eye, Plus, ReceiptText, Search, Wallet } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

type Payment = { date: string; amount: number; method: string; reference: string };
type Bill = { id: number; party: string; studentId: string; group: string; category: string; description: string; amount: number; due: string; payments: Payment[] };
type Mode = 'student' | 'institute';
const demoStudents = [
    { id: 'STU-1001', name: 'Ayesha Rahman', batch: 'CMUD · Batch 01' },
    { id: 'STU-1002', name: 'Tanvir Ahmed', batch: 'CMUD · Batch 01' },
    { id: 'STU-2001', name: 'Mehedi Hasan', batch: 'CMUD · Batch 02' },
];
// Amounts use integer poisha so demo totals and partial payments stay consistent.
const studentBills: Bill[] = [
    { id: 1, party: 'Ayesha Rahman', studentId: 'STU-1001', group: 'CMUD · Batch 01', category: 'Tuition', description: 'November tuition installment', amount: 1200000, due: '2026-11-30', payments: [{ date: '2026-11-15', amount: 600000, method: 'Bank transfer', reference: 'DEMO-TXN-001' }] },
    { id: 2, party: 'Tanvir Ahmed', studentId: 'STU-1002', group: 'CMUD · Batch 01', category: 'Tuition', description: 'November tuition installment', amount: 1200000, due: '2026-11-30', payments: [] },
    { id: 3, party: 'Ayesha Rahman', studentId: 'STU-1001', group: 'CMUD · Batch 01', category: 'Other', description: 'Module examination fee', amount: 200000, due: '2026-11-25', payments: [{ date: '2026-11-18', amount: 200000, method: 'Cash', reference: 'DEMO-RCPT-002' }] },
    { id: 4, party: 'Mehedi Hasan', studentId: 'STU-2001', group: 'CMUD · Batch 02', category: 'Other', description: 'Certificate processing fee', amount: 150000, due: '2026-12-05', payments: [] },
];
const instituteBills: Bill[] = [
    { id: 1, party: 'Dhaka Property Services', studentId: '', group: 'CMUD · Main campus', category: 'Rent', description: 'November campus rent', amount: 4500000, due: '2026-11-30', payments: [] },
    { id: 2, party: 'City Utility Services', studentId: '', group: 'CMUD · Main campus', category: 'Utilities', description: 'Electricity and water bill', amount: 850000, due: '2026-11-25', payments: [{ date: '2026-11-20', amount: 850000, method: 'Bank transfer', reference: 'DEMO-PAY-001' }] },
    { id: 3, party: 'Medical Training Supplies', studentId: '', group: 'CMUD · Main campus', category: 'Supplies', description: 'Training materials and stationery', amount: 1800000, due: '2026-12-02', payments: [{ date: '2026-11-19', amount: 500000, method: 'Bank transfer', reference: 'DEMO-PAY-002' }] },
];
const money = (poisha: number) => `৳ ${new Intl.NumberFormat('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(poisha / 100)}`;
const paid = (bill: Bill) => bill.payments.reduce((sum, payment) => sum + payment.amount, 0);
const billStatus = (bill: Bill) => paid(bill) >= bill.amount ? 'Paid' : paid(bill) > 0 ? 'Partial' : 'Unpaid';
const field = 'mt-1.5 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/10';
const primary = 'inline-flex items-center justify-center gap-2 rounded-xl bg-[#17375C] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#254878]';

export default function BillingWorkspace({ mode }: { mode: Mode }) {
    const isStudent = mode === 'student';
    const title = isStudent ? 'Student payment' : 'Institute billing';
    const url = isStudent ? '/billings/student-bill' : '/billings/institute-billing';
    const categories = isStudent ? ['Tuition', 'Other'] : ['Rent', 'Utilities', 'Supplies', 'Maintenance', 'Other'];
    const [bills, setBills] = useState<Bill[]>(isStudent ? studentBills : instituteBills);
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState('All');
    const [status, setStatus] = useState('All');
    const [group, setGroup] = useState('All');
    const [dialog, setDialog] = useState<'create' | 'pay' | 'view' | null>(null);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [form, setForm] = useState({ studentId: '', party: '', group: '', category: categories[0], description: '', amount: '', date: '', method: 'Cash', reference: '' });
    const [notice, setNotice] = useState('');
    const [error, setError] = useState('');
    const selected = bills.find((bill) => bill.id === selectedId);
    const billNumber = (id: number) => `DEMO-${isStudent ? 'STU' : 'INST'}-${String(id).padStart(4, '0')}`;
    const visible = bills.filter((bill) => (category === 'All' || bill.category === category) && (status === 'All' || billStatus(bill) === status) && (group === 'All' || bill.group === group) && `${bill.party} ${bill.studentId} ${bill.description} ${billNumber(bill.id)}`.toLowerCase().includes(query.trim().toLowerCase()));
    const totals = visible.reduce((sum, bill) => ({ billed: sum.billed + bill.amount, paid: sum.paid + paid(bill) }), { billed: 0, paid: 0 });

    function open(action: 'create' | 'pay' | 'view', bill?: Bill) {
        setDialog(action);
        setSelectedId(bill?.id ?? null);
        setForm({ studentId: '', party: '', group: '', category: categories[0], description: '', amount: '', date: '', method: 'Cash', reference: '' });
        setError('');
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const amount = Math.round(Number(form.amount) * 100);
        if (!Number.isFinite(amount) || amount <= 0) { setError('Enter an amount greater than zero.'); return; }
        if (dialog === 'create') {
            const student = demoStudents.find((item) => item.id === form.studentId);
            if ((isStudent && !student) || (!isStudent && (!form.party.trim() || !form.group.trim())) || !form.description.trim() || !form.date) { setError('Complete all required bill details.'); return; }
            setBills((current) => [...current, { id: Math.max(0, ...current.map((bill) => bill.id)) + 1, party: student?.name ?? form.party.trim(), studentId: student?.id ?? '', group: student?.batch ?? form.group.trim(), category: form.category, description: form.description.trim(), amount, due: form.date, payments: [] }]);
        } else if (dialog === 'pay' && selected) {
            if (amount > selected.amount - paid(selected)) { setError('Payment cannot exceed the remaining balance.'); return; }
            if (!form.date || (selected.payments.length > 0 && form.date < selected.payments[selected.payments.length - 1].date)) { setError('Enter a payment date on or after the latest payment.'); return; }
            setBills((current) => current.map((bill) => bill.id === selected.id ? { ...bill, payments: [...bill.payments, { date: form.date, amount, method: form.method, reference: form.reference.trim() }] } : bill));
        } else { setError('Choose a student payment.'); return; }
        setDialog(null);
        setNotice('Demo register updated. Changes reset when you refresh or leave this page.');
    }

    function input(name: 'party' | 'group' | 'description' | 'amount' | 'date' | 'reference', label: string, type = 'text', required = true) {
        return <label className="block text-xs font-medium text-slate-600">{label}<input type={type} required={required} value={form[name]} min={type === 'number' ? '0.01' : type === 'date' && dialog === 'pay' && selected?.payments.length ? selected.payments[selected.payments.length - 1].date : undefined} step={type === 'number' ? '0.01' : undefined} max={type === 'number' && dialog === 'pay' && selected ? (selected.amount - paid(selected)) / 100 : undefined} onChange={(event) => { setForm({ ...form, [name]: event.target.value }); setError(''); }} className={field} /></label>;
    }

    return <AppLayout breadcrumbs={[{ title: 'Billings', href: '/billings/student-bill' }, { title, href: url }]}>
        <Head title={title} />
        <main className="min-h-screen bg-[#F7F9FC] px-4 py-6 text-slate-800 sm:px-7 lg:px-10"><div className="mx-auto max-w-7xl space-y-6">
            <header className="flex flex-wrap items-center justify-between gap-5 rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-6 text-white sm:p-8">
                <div><p className="flex items-center gap-2 text-xs font-semibold tracking-[0.16em] text-cyan-100 uppercase"><ReceiptText size={16} /> Billings</p><h1 className="mt-3 text-3xl font-semibold tracking-tight">{title}</h1><p className="mt-2 text-sm text-cyan-50/85">{isStudent ? 'Manage student tuition and other payment records.' : 'Manage institute bills, supplier payments, and outstanding balances.'}</p><p className="mt-4 text-xs text-cyan-100">Demo workspace · Sample data · No payments are processed</p></div>
                <button type="button" onClick={() => open(isStudent ? 'pay' : 'create')} className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-[#17375C] hover:bg-cyan-50"><Plus size={17} /> {isStudent ? 'Create payment' : 'Create bill'}</button>
            </header>
            {!isStudent && <div className="grid gap-4 sm:grid-cols-3">{[
                { label: 'Total billed', value: totals.billed, icon: ReceiptText },
                { label: 'Payments made', value: totals.paid, icon: Banknote },
                { label: 'Outstanding', value: totals.billed - totals.paid, icon: Wallet },
            ].map(({ label, value, icon: Icon }) => <div key={label} className="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5"><span className="rounded-xl bg-teal-50 p-3 text-teal-700"><Icon size={21} /></span><div><p className="text-xs text-slate-500">{label}</p><p className="mt-1 text-xl font-semibold text-[#17375C]">{money(value)}</p></div></div>)}</div>}
            {notice && <p role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{notice}</p>}
            <section className="rounded-2xl border border-slate-200 bg-white p-5" aria-label={isStudent ? 'Payment filters' : 'Bill filters'}>
                {isStudent && <div className="mb-5 flex flex-wrap gap-2" aria-label="Payment type">{['All', ...categories].map((value) => <button key={value} type="button" aria-pressed={category === value} onClick={() => setCategory(value)} className={`rounded-lg px-4 py-2 text-sm font-medium ${category === value ? 'bg-[#17375C] text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'}`}>{value === 'All' ? 'All payments' : value === 'Tuition' ? 'Tuition payments' : 'Other payments'}</button>)}</div>}
                <div className={`grid gap-4 sm:grid-cols-2 ${isStudent ? 'lg:grid-cols-3' : 'lg:grid-cols-4'}`}>
                    <label className="text-xs font-medium text-slate-600">Search<div className="relative"><Search size={16} className="absolute top-5 left-3 text-slate-400" /><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder={isStudent ? 'Student name, ID, or payment' : 'Supplier, bill number, or description'} className={`${field} pl-9`} /></div></label>
                    <label className="text-xs font-medium text-slate-600">{isStudent ? 'Batch' : 'Institute / branch'}<select value={group} onChange={(event) => setGroup(event.target.value)} className={field}><option value="All">{isStudent ? 'All batches' : 'All locations'}</option>{[...new Set(bills.map((bill) => bill.group))].map((value) => <option key={value}>{value}</option>)}</select></label>
                    {!isStudent && <label className="text-xs font-medium text-slate-600">Category<select value={category} onChange={(event) => setCategory(event.target.value)} className={field}>{['All', ...categories].map((value) => <option key={value}>{value}</option>)}</select></label>}
                    <label className="text-xs font-medium text-slate-600">Status<select value={status} onChange={(event) => setStatus(event.target.value)} className={field}>{['All', 'Unpaid', 'Partial', 'Paid'].map((value) => <option key={value}>{value}</option>)}</select></label>
                </div><p className="mt-3 text-xs text-slate-400">{!isStudent && 'Totals reflect the current filters. '}All amounts are in BDT.</p>
            </section>
            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white" aria-labelledby="bill-register"><div className="border-b border-slate-100 px-6 py-5"><h2 id="bill-register" className="font-semibold text-[#17375C]">{isStudent ? 'Student payments' : 'Institute bills'} <span className="ml-2 text-sm font-normal text-slate-400">{visible.length} records</span></h2></div>
                <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-slate-50 text-xs text-slate-500"><tr>{[isStudent ? 'Payment / description' : 'Bill / description', isStudent ? 'Student' : 'Supplier / payee', 'Due date', 'Amount', 'Balance', 'Status', 'Actions'].map((label) => <th key={label} className="whitespace-nowrap px-5 py-3 font-medium">{label}</th>)}</tr></thead><tbody className="divide-y divide-slate-100">
                    {visible.map((bill) => <tr key={bill.id}><td className="px-5 py-4"><p className="whitespace-nowrap font-mono text-xs text-[#17375C]">{billNumber(bill.id)}</p><p className="mt-1 min-w-40 text-xs text-slate-600">{bill.description}</p><p className="mt-1 text-xs text-slate-400">{bill.category}</p></td><td className="px-5 py-4"><p className="whitespace-nowrap font-medium text-[#17375C]">{bill.party}</p><p className="mt-1 whitespace-nowrap text-xs text-slate-500">{bill.studentId && `${bill.studentId} · `}{bill.group}</p></td><td className="whitespace-nowrap px-5 py-4 text-xs text-slate-500">{bill.due}</td><td className="whitespace-nowrap px-5 py-4 text-xs">{money(bill.amount)}</td><td className="whitespace-nowrap px-5 py-4 text-xs font-semibold">{money(bill.amount - paid(bill))}</td><td className="px-5 py-4"><span className={`rounded-full px-2.5 py-1 text-xs font-medium ${billStatus(bill) === 'Paid' ? 'bg-emerald-50 text-emerald-700' : billStatus(bill) === 'Partial' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600'}`}>{billStatus(bill)}</span></td><td className="px-5 py-4"><div className="flex gap-2"><button type="button" onClick={() => open('view', bill)} aria-label={`View ${isStudent ? 'payment' : 'bill'} ${bill.id}`} className="rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-50"><Eye size={16} /></button>{paid(bill) < bill.amount && <button type="button" onClick={() => open('pay', bill)} className="whitespace-nowrap rounded-lg border border-teal-200 bg-teal-50 px-3 py-2 text-xs font-semibold text-teal-800">{isStudent ? 'Receive payment' : 'Record payment'}</button>}</div></td></tr>)}
                    {!visible.length && <tr><td colSpan={7} className="px-6 py-12 text-center text-slate-500">{isStudent ? 'No payments match your filters.' : 'No bills match your filters.'}</td></tr>}
                </tbody></table></div>
            </section>
        </div></main>
        <Dialog open={dialog !== null} onOpenChange={(open) => { if (!open) setDialog(null); }}><DialogContent className="max-h-[85svh] overflow-y-auto sm:max-w-xl"><DialogHeader><DialogTitle>{dialog === 'create' ? 'Create bill' : dialog === 'view' ? (isStudent ? 'Payment details & history' : 'Bill details & payment history') : isStudent ? 'Create payment' : 'Record institute payment'}</DialogTitle><DialogDescription>Demo only. Entries stay on this page until you refresh or navigate away.</DialogDescription></DialogHeader>
            {selected && <div className="space-y-1 rounded-xl bg-slate-50 p-4"><p className="font-semibold text-[#17375C]">{selected.party}</p><p className="text-xs text-slate-500">{selected.description} · {selected.category}</p><p className="text-xs text-slate-500">{selected.group} · Due {selected.due}</p><p className="pt-2 text-sm">{isStudent ? 'Payable' : 'Bill'}: {money(selected.amount)} · Balance: <strong>{money(selected.amount - paid(selected))}</strong></p></div>}
            {dialog === 'view' && selected ? <div className="space-y-3"><h3 className="text-sm font-semibold text-[#17375C]">Payment history</h3>{selected.payments.length ? selected.payments.map((payment, index) => <div key={index} className="flex justify-between gap-4 border-b border-slate-100 py-3 text-sm"><div><p>{payment.date} · {payment.method}</p><p className="mt-1 text-xs text-slate-500">{payment.reference || 'No reference provided'}</p></div><strong className="whitespace-nowrap text-teal-700">{money(payment.amount)}</strong></div>) : <p className="text-sm text-slate-500">No payments recorded yet.</p>}</div> : <form onSubmit={submit} className="space-y-4">
                {isStudent && dialog === 'pay' && <label className="block text-xs font-medium text-slate-600">Student payment<select required value={selectedId ?? ''} onChange={(event) => { setSelectedId(Number(event.target.value) || null); setError(''); }} className={field}><option value="">Choose student payment</option>{bills.filter((bill) => paid(bill) < bill.amount).map((bill) => <option key={bill.id} value={bill.id}>{bill.party} ? {bill.studentId} ? {bill.description} ? Balance {money(bill.amount - paid(bill))}</option>)}</select></label>}
                {dialog === 'create' && <>
                    {isStudent ? <label className="block text-xs font-medium text-slate-600">Student<select required value={form.studentId} onChange={(event) => setForm({ ...form, studentId: event.target.value })} className={field}><option value="">Choose student</option>{demoStudents.map((student) => <option key={student.id} value={student.id}>{student.id} · {student.name} · {student.batch}</option>)}</select></label> : <>{input('party', 'Supplier / payee')}{input('group', 'Institute / branch')}</>}
                    <label className="block text-xs font-medium text-slate-600">Bill category<select value={form.category} onChange={(event) => setForm({ ...form, category: event.target.value })} className={field}>{categories.map((value) => <option key={value}>{value}</option>)}</select></label>
                    {input('description', 'Bill description')}
                </>}
                <div className="grid gap-4 sm:grid-cols-2">{input('amount', dialog === 'create' ? 'Bill amount (BDT)' : 'Payment amount (BDT)', 'number')}{input('date', dialog === 'create' ? 'Due date' : 'Payment date', 'date')}</div>
                {dialog === 'pay' && <><label className="block text-xs font-medium text-slate-600">Payment method<select value={form.method} onChange={(event) => setForm({ ...form, method: event.target.value })} className={field}>{['Cash', 'Bank transfer', 'Mobile banking', 'Cheque'].map((value) => <option key={value}>{value}</option>)}</select></label>{input('reference', 'Transaction / receipt reference (optional)', 'text', false)}<p className="text-xs text-slate-500">Partial payments are supported. No money will be transferred.</p></>}
                {error && <p role="alert" className="text-sm text-rose-700">{error}</p>}
                <div className="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" onClick={() => setDialog(null)} className="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-600">Cancel</button><button type="submit" className={primary}><CreditCard size={16} />{dialog === 'create' ? 'Save demo bill' : 'Save demo payment'}</button></div>
            </form>}
        </DialogContent></Dialog>
    </AppLayout>;
}
