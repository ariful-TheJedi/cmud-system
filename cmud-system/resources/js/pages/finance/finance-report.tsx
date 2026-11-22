import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { ReceiptText, RotateCcw } from 'lucide-react';
import { useState } from 'react';

type Mode = 'revenue' | 'expenditure';
type Transaction = { id: string; mode: Mode; date: string; party: string; batch: string | null; category: string; description: string; amount: number };

// Sample transactions use integer poisha. Replace this source with API data later.
const transactions: Transaction[] = [
    { id: 'REV-001', mode: 'revenue', date: '2026-11-15', party: 'Ayesha Rahman', batch: 'Batch 01', category: 'Tuition', description: 'November tuition installment', amount: 600000 },
    { id: 'REV-002', mode: 'revenue', date: '2026-11-18', party: 'Ayesha Rahman', batch: 'Batch 01', category: 'Other', description: 'Module examination fee', amount: 200000 },
    { id: 'REV-003', mode: 'revenue', date: '2026-10-12', party: 'Tanvir Ahmed', batch: 'Batch 01', category: 'Tuition', description: 'October tuition installment', amount: 1200000 },
    { id: 'REV-004', mode: 'revenue', date: '2026-10-20', party: 'Mehedi Hasan', batch: 'Batch 02', category: 'Tuition', description: 'October tuition installment', amount: 900000 },
    { id: 'REV-005', mode: 'revenue', date: '2025-12-10', party: 'Mehedi Hasan', batch: 'Batch 02', category: 'Other', description: 'Admission payment', amount: 300000 },
    { id: 'EXP-001', mode: 'expenditure', date: '2026-11-20', party: 'City Utility Services', batch: null, category: 'Utilities', description: 'Electricity and water payment', amount: 850000 },
    { id: 'EXP-002', mode: 'expenditure', date: '2026-11-19', party: 'Medical Training Supplies', batch: 'Batch 01', category: 'Supplies', description: 'Training materials payment', amount: 500000 },
    { id: 'EXP-003', mode: 'expenditure', date: '2026-10-05', party: 'Dhaka Property Services', batch: null, category: 'Rent', description: 'October campus rent', amount: 4500000 },
    { id: 'EXP-004', mode: 'expenditure', date: '2026-10-15', party: 'Medical Training Supplies', batch: 'Batch 02', category: 'Supplies', description: 'Practical training materials', amount: 750000 },
    { id: 'EXP-005', mode: 'expenditure', date: '2025-12-18', party: 'Campus Maintenance Services', batch: null, category: 'Maintenance', description: 'Classroom repairs', amount: 400000 },
];
const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const money = (amount: number) => `৳ ${new Intl.NumberFormat('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount / 100)}`;
const selectClass = 'mt-2 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/10';

export default function FinanceReport({ mode }: { mode: Mode }) {
    const isRevenue = mode === 'revenue';
    const title = isRevenue ? 'Student revenue' : 'Institute expenditure';
    const href = `/finance/${isRevenue ? 'student-revenue' : 'institute-expenditure'}`;
    const [year, setYear] = useState('All');
    const [month, setMonth] = useState('All');
    const [dateMode, setDateMode] = useState<'single' | 'range'>('single');
    const [singleDate, setSingleDate] = useState('');
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [batch, setBatch] = useState('All');
    const source = transactions.filter((row) => row.mode === mode);
    const years = [...new Set(source.map((row) => row.date.slice(0, 4)))].sort().reverse();
    const batches = [...new Set(source.flatMap((row) => row.batch ? [row.batch] : []))].sort();
    const visible = source.filter((row) => (year === 'All' || row.date.slice(0, 4) === year) && (month === 'All' || row.date.slice(5, 7) === month) && (dateMode === 'single' ? (!singleDate || row.date === singleDate) : ((!startDate || row.date >= startDate) && (!endDate || row.date <= endDate))) && (batch === 'All' || (batch === 'General' ? row.batch === null : row.batch === batch))).sort((a, b) => b.date.localeCompare(a.date));
    const total = visible.reduce((sum, row) => sum + row.amount, 0);
    const monthly = [...new Set(visible.map((row) => row.date.slice(0, 7)))].sort().reverse().map((period) => ({ period, amount: visible.filter((row) => row.date.startsWith(period)).reduce((sum, row) => sum + row.amount, 0) }));

    return <AppLayout breadcrumbs={[{ title: 'Finance', href: '/finance' }, { title, href }]}>
        <Head title={title} />
        <main className="min-h-screen bg-[#F7F9FC] px-4 py-6 text-slate-800 sm:px-7 lg:px-10">
            <div className="mx-auto max-w-7xl space-y-6">
                <header className="rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-6 text-white sm:p-8">
                    <p className="flex items-center gap-2 text-xs font-semibold tracking-widest text-cyan-100 uppercase"><ReceiptText size={16} /> Finance</p>
                    <h1 className="mt-3 text-3xl font-semibold">{title}</h1>
                    <p className="mt-2 text-sm text-cyan-50">{isRevenue ? 'Calculate income from student payments received.' : 'Calculate institute expenses from payments made.'} Filter by a single date or date range, month, batch, and year.</p>
                    <p className="mt-4 text-xs text-cyan-100">Demo report · Sample data · API integration pending</p>
                </header>
                <section aria-label="Finance filters" className="rounded-2xl border border-slate-200 bg-white p-5">
                    <div className="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label className="text-sm font-medium">Year<select value={year} onChange={(event) => setYear(event.target.value)} className={selectClass}><option value="All">All years</option>{years.map((value) => <option key={value}>{value}</option>)}</select></label>
                        <label className="text-sm font-medium">Month<select value={month} onChange={(event) => setMonth(event.target.value)} className={selectClass}><option value="All">All months</option>{months.map((name, index) => <option key={name} value={String(index + 1).padStart(2, '0')}>{name}</option>)}</select></label>
                        <label className="text-sm font-medium">Date filter<select value={dateMode} onChange={(event) => setDateMode(event.target.value as 'single' | 'range')} className={selectClass}><option value="single">Single date</option><option value="range">Date range</option></select></label>
                        {dateMode === 'single' ? <label className="text-sm font-medium">Date<input type="date" value={singleDate} onChange={(event) => setSingleDate(event.target.value)} className={selectClass} /></label> : <>
                        <label className="text-sm font-medium">Start date<input type="date" value={startDate} max={endDate || undefined} onChange={(event) => setStartDate(event.target.value)} className={selectClass} /></label>
                        <label className="text-sm font-medium">End date<input type="date" value={endDate} min={startDate || undefined} onChange={(event) => setEndDate(event.target.value)} className={selectClass} /></label></>}
                        <label className="text-sm font-medium">Batch<select value={batch} onChange={(event) => setBatch(event.target.value)} className={selectClass}><option value="All">All batches{!isRevenue && ' & general expenses'}</option>{batches.map((value) => <option key={value}>{value}</option>)}{!isRevenue && <option value="General">General institute expenses</option>}</select></label>
                        <button type="button" onClick={() => { setYear('All'); setMonth('All'); setDateMode('single'); setSingleDate(''); setStartDate(''); setEndDate(''); setBatch('All'); }} className="flex h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 text-sm font-medium hover:bg-slate-50"><RotateCcw size={16} /> Reset filters</button>
                    </div>
                    <p className="mt-3 text-xs text-slate-500">{!isRevenue && 'General institute expenses are excluded when a specific batch is selected. '}Weeks run Monday–Sunday. Leave the week empty for all weeks. Filters apply together. Amounts are in BDT.</p>
                </section>
                <section aria-label="Financial summary" className="grid gap-4 sm:grid-cols-3">
                    {[{ label: isRevenue ? 'Total revenue received' : 'Total expenditure paid', value: money(total) }, { label: 'Transactions', value: visible.length }, { label: 'Monthly average', value: money(monthly.length ? Math.round(total / monthly.length) : 0) }].map((item) => <div key={item.label} className="rounded-2xl border border-slate-200 bg-white p-5"><p className="text-sm text-slate-500">{item.label}</p><p className="mt-2 text-2xl font-semibold text-[#17375C]">{item.value}</p>{item.label === 'Monthly average' && <p className="mt-2 text-xs text-slate-500">Across months with transactions</p>}</div>)}
                </section>
                <section aria-labelledby="monthly-summary" className="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 id="monthly-summary" className="font-semibold text-[#17375C]">Monthly breakdown</h2>
                    <div className="mt-4 space-y-4">{monthly.map((item) => <div key={item.period}><div className="flex flex-wrap justify-between gap-2 text-sm"><span>{months[Number(item.period.slice(5)) - 1]} {item.period.slice(0, 4)}</span><span className="font-semibold">{money(item.amount)}</span></div><div className="mt-2 h-2 rounded-full bg-slate-100"><div className="h-2 rounded-full bg-teal-600" style={{ width: `${total ? item.amount / total * 100 : 0}%` }} /></div></div>)}{!monthly.length && <p className="py-4 text-sm text-slate-500">No transactions for the selected filters.</p>}</div>
                </section>
                <section aria-labelledby="transactions" className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <h2 id="transactions" className="border-b border-slate-100 px-6 py-5 font-semibold text-[#17375C]">{isRevenue ? 'Revenue transactions' : 'Expenditure transactions'}</h2>
                    <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-slate-50 text-slate-500"><tr>{['Date / reference', isRevenue ? 'Student' : 'Payee', 'Batch', 'Category / description', 'Amount'].map((label) => <th key={label} className="whitespace-nowrap px-5 py-3 font-medium">{label}</th>)}</tr></thead><tbody className="divide-y divide-slate-100">{visible.map((row) => <tr key={row.id}><td className="whitespace-nowrap px-5 py-4">{row.date}<p className="mt-1 text-xs text-slate-500">{row.id}</p></td><td className="px-5 py-4 font-medium">{row.party}</td><td className="whitespace-nowrap px-5 py-4">{row.batch ?? 'General institute'}</td><td className="px-5 py-4">{row.category}<p className="mt-1 text-xs text-slate-500">{row.description}</p></td><td className="whitespace-nowrap px-5 py-4 font-semibold text-[#17375C]">{money(row.amount)}</td></tr>)}{!visible.length && <tr><td colSpan={5} className="px-5 py-12 text-center text-slate-500">No transactions match your filters.</td></tr>}</tbody><tfoot className="border-t border-slate-200 bg-slate-50 font-semibold"><tr><td colSpan={4} className="px-5 py-4">Filtered total</td><td className="whitespace-nowrap px-5 py-4">{money(total)}</td></tr></tfoot></table></div>
                </section>
            </div>
        </main>
    </AppLayout>;
}
