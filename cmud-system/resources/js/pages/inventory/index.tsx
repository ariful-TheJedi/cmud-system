import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { ArrowDownToLine, ArrowUpFromLine, Boxes, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

type InventoryItem = {
    id: number;
    code: string;
    name: string;
    category: string;
    institute: string;
    branch: string;
    quantity: number;
    unit: string;
    lowStockAt: number;
};

type InventoryActivity = {
    id: number;
    itemName: string;
    code: string;
    action: 'item_added' | 'stock_added' | 'stock_removed';
    quantity: number;
    unit: string;
    institute: string;
    branch: string;
    actor: string;
    date: string;
    note: string;
};

const institutes = ['CMUD Core Development Institute', 'CMUD Sylhet Demo Institute'];
const branches = ['Dhaka Demo Branch', 'Chattogram Demo Branch', 'Sylhet Demo Branch'];

// Keep realistic records local until the Inventory API is ready.
const sampleItems: InventoryItem[] = [
    { id: 1, code: 'EQ-ULS-001', name: 'Portable ultrasound training unit', category: 'Medical equipment', institute: institutes[0], branch: 'Dhaka Demo Branch', quantity: 4, unit: 'units', lowStockAt: 1 },
    { id: 2, code: 'EQ-PRJ-004', name: 'Classroom projector', category: 'IT equipment', institute: institutes[0], branch: 'Chattogram Demo Branch', quantity: 2, unit: 'units', lowStockAt: 2 },
    { id: 3, code: 'SUP-GLV-012', name: 'Disposable examination gloves', category: 'Medical supplies', institute: institutes[0], branch: 'Sylhet Demo Branch', quantity: 18, unit: 'boxes', lowStockAt: 5 },
    { id: 4, code: 'SUP-PPR-002', name: 'A4 printing paper', category: 'Office supplies', institute: institutes[0], branch: 'Institute-wide', quantity: 7, unit: 'reams', lowStockAt: 3 },
    { id: 5, code: 'EQ-LTP-008', name: 'Staff laptop', category: 'IT equipment', institute: institutes[1], branch: 'Sylhet Demo Branch', quantity: 3, unit: 'units', lowStockAt: 1 },
    { id: 6, code: 'SUP-MSK-006', name: 'Surgical masks', category: 'Medical supplies', institute: institutes[1], branch: 'Sylhet Demo Branch', quantity: 2, unit: 'boxes', lowStockAt: 4 },
];

const sampleActivities: InventoryActivity[] = [
    { id: 1, itemName: 'Disposable examination gloves', code: 'SUP-GLV-012', action: 'stock_added', quantity: 10, unit: 'boxes', institute: institutes[0], branch: 'Sylhet Demo Branch', actor: 'Nusrat Jahan', date: '2026-11-24', note: 'Monthly medical supply restock' },
    { id: 2, itemName: 'Classroom projector', code: 'EQ-PRJ-004', action: 'stock_removed', quantity: 1, unit: 'unit', institute: institutes[0], branch: 'Chattogram Demo Branch', actor: 'Imran Hossain', date: '2026-11-23', note: 'Assigned to classroom B-02' },
    { id: 3, itemName: 'Portable ultrasound training unit', code: 'EQ-ULS-001', action: 'item_added', quantity: 4, unit: 'units', institute: institutes[0], branch: 'Dhaka Demo Branch', actor: 'Farhana Akter', date: '2026-11-21', note: 'New resource registered' },
    { id: 4, itemName: 'Surgical masks', code: 'SUP-MSK-006', action: 'stock_removed', quantity: 1, unit: 'box', institute: institutes[1], branch: 'Sylhet Demo Branch', actor: 'Rafiq Hasan', date: '2026-11-20', note: 'Issued for practical session' },
    { id: 5, itemName: 'Staff laptop', code: 'EQ-LTP-008', action: 'stock_added', quantity: 2, unit: 'units', institute: institutes[1], branch: 'Sylhet Demo Branch', actor: 'Maliha Sultana', date: '2026-11-18', note: 'New stock received' },
];

const inputClass = 'h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/10';
const formatDate = (date: string) => new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${date}T00:00:00`));

/** Show institute resources, branch quantities, and a sample stock activity ledger. */
export default function InventoryPage() {
    const [institute, setInstitute] = useState('All institutes');
    const [branch, setBranch] = useState('All branches');
    const [search, setSearch] = useState('');

    const filteredItems = useMemo(() => sampleItems.filter((item) =>
        (institute === 'All institutes' || item.institute === institute)
        && (branch === 'All branches' || item.branch === branch)
        && `${item.name} ${item.code} ${item.category}`.toLowerCase().includes(search.trim().toLowerCase()),
    ), [institute, branch, search]);

    const filteredActivities = useMemo(() => sampleActivities.filter((activity) =>
        (institute === 'All institutes' || activity.institute === institute)
        && (branch === 'All branches' || activity.branch === branch)
        && `${activity.itemName} ${activity.code} ${activity.note} ${activity.actor}`.toLowerCase().includes(search.trim().toLowerCase()),
    ), [institute, branch, search]);

    const lowStockCount = filteredItems.filter((item) => item.quantity <= item.lowStockAt).length;

    return (
        <AppLayout breadcrumbs={[{ title: 'Inventory', href: '/inventory' }]}>
            <Head title="Inventory" />
            <main className="min-h-screen bg-[#F7F9FC] px-4 py-6 text-slate-800 sm:px-7 lg:px-10">
                <div className="mx-auto max-w-7xl space-y-6">
                    <header className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-[#17375C]">Inventory</h1>
                            <p className="mt-2 text-sm text-slate-500">Institute resources, branch stock, and inventory activity.</p>
                        </div>
                    </header>

                    <section className="grid gap-4 sm:grid-cols-3" aria-label="Inventory overview">
                        <SummaryCard label="Resources shown" value={String(filteredItems.length)} icon={<Boxes size={20} />} />
                        <SummaryCard label="Activity records" value={String(filteredActivities.length)} icon={<ArrowDownToLine size={20} />} />
                        <SummaryCard label="Low stock items" value={String(lowStockCount)} icon={<ArrowUpFromLine size={20} />} warning={lowStockCount > 0} />
                    </section>

                    <section className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:grid-cols-[1fr_1fr_1.4fr]" aria-label="Inventory filters">
                        <label className="text-xs font-medium text-slate-600">Institute
                            <select value={institute} onChange={(event) => setInstitute(event.target.value)} className={`${inputClass} mt-1.5`}>
                                <option>All institutes</option>{institutes.map((name) => <option key={name}>{name}</option>)}
                            </select>
                        </label>
                        <label className="text-xs font-medium text-slate-600">Branch
                            <select value={branch} onChange={(event) => setBranch(event.target.value)} className={`${inputClass} mt-1.5`}>
                                <option>All branches</option><option>Institute-wide</option>{branches.map((name) => <option key={name}>{name}</option>)}
                            </select>
                        </label>
                        <label className="text-xs font-medium text-slate-600">Search resources and activity
                            <span className="relative mt-1.5 block"><Search size={16} className="absolute top-3 left-3 text-slate-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Item, code, category, or activity" className={`${inputClass} pl-9`} /></span>
                        </label>
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white" aria-labelledby="inventory-items-title">
                        <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <div><h2 id="inventory-items-title" className="font-semibold text-[#17375C]">Resources and stock</h2><p className="mt-1 text-xs text-slate-500">{filteredItems.length} resources matching the selected locations</p></div>
                        </div>
                        <div className="overflow-x-auto"><table className="w-full min-w-[780px] text-left text-sm"><thead className="bg-slate-50 text-xs text-slate-500"><tr>{['Resource', 'Category', 'Institute', 'Branch', 'Quantity', 'Stock status'].map((label) => <th key={label} className="px-5 py-3 font-medium">{label}</th>)}</tr></thead><tbody className="divide-y divide-slate-100">
                            {filteredItems.map((item) => <tr key={item.id}><td className="px-5 py-4"><p className="font-medium text-[#17375C]">{item.name}</p><p className="mt-1 font-mono text-[11px] text-slate-500">{item.code}</p></td><td className="px-5 py-4 text-slate-600">{item.category}</td><td className="px-5 py-4 text-slate-600">{item.institute}</td><td className="px-5 py-4 text-slate-600">{item.branch}</td><td className="whitespace-nowrap px-5 py-4 font-semibold tabular-nums text-slate-800">{item.quantity} <span className="font-normal text-slate-500">{item.unit}</span></td><td className="px-5 py-4"><span className={`rounded-full px-2.5 py-1 text-xs font-medium ${item.quantity <= item.lowStockAt ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'}`}>{item.quantity <= item.lowStockAt ? 'Low stock' : 'In stock'}</span></td></tr>)}
                            {!filteredItems.length && <tr><td colSpan={6} className="px-5 py-10 text-center text-sm text-slate-500">No inventory resources match these filters.</td></tr>}
                        </tbody></table></div>
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white" aria-labelledby="inventory-activity-title">
                        <div className="border-b border-slate-100 px-5 py-4"><h2 id="inventory-activity-title" className="font-semibold text-[#17375C]">Stock activity</h2><p className="mt-1 text-xs text-slate-500">Sample history of new resources, stock received, and stock issued or removed.</p></div>
                        <div className="overflow-x-auto"><table className="w-full min-w-[780px] text-left text-sm"><thead className="bg-slate-50 text-xs text-slate-500"><tr>{['Date', 'Activity', 'Resource', 'Institute / branch', 'Quantity', 'Recorded by', 'Note'].map((label) => <th key={label} className="px-5 py-3 font-medium">{label}</th>)}</tr></thead><tbody className="divide-y divide-slate-100">
                            {filteredActivities.map((activity) => <tr key={activity.id}><td className="whitespace-nowrap px-5 py-4 text-slate-600">{formatDate(activity.date)}</td><td className="px-5 py-4"><ActivityBadge action={activity.action} /></td><td className="px-5 py-4"><p className="font-medium text-slate-800">{activity.itemName}</p><p className="mt-1 font-mono text-[11px] text-slate-500">{activity.code}</p></td><td className="px-5 py-4"><p className="text-slate-700">{activity.institute}</p><p className="mt-1 text-xs text-slate-500">{activity.branch}</p></td><td className="whitespace-nowrap px-5 py-4 tabular-nums">{activity.action === 'stock_removed' ? '−' : '+'}{activity.quantity} {activity.unit}</td><td className="whitespace-nowrap px-5 py-4 text-slate-600">{activity.actor}</td><td className="px-5 py-4 text-slate-600">{activity.note}</td></tr>)}
                            {!filteredActivities.length && <tr><td colSpan={7} className="px-5 py-10 text-center text-sm text-slate-500">No inventory activity matches these filters.</td></tr>}
                        </tbody></table></div>
                    </section>
                </div>
            </main>
        </AppLayout>
    );
}

/** Render a compact summary tile with optional low-stock emphasis. */
function SummaryCard({ label, value, icon, warning = false }: { label: string; value: string; icon: React.ReactNode; warning?: boolean }) {
    return <div className="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5"><span className={`rounded-xl p-3 ${warning ? 'bg-amber-50 text-amber-700' : 'bg-teal-50 text-teal-700'}`}>{icon}</span><div><p className="text-xs text-slate-500">{label}</p><p className="mt-1 text-xl font-semibold text-[#17375C]">{value}</p></div></div>;
}

/** Label the ledger event type without hiding the signed quantity from users. */
function ActivityBadge({ action }: { action: InventoryActivity['action'] }) {
    const style = action === 'stock_removed' ? 'bg-rose-50 text-rose-700' : action === 'stock_added' ? 'bg-emerald-50 text-emerald-700' : 'bg-sky-50 text-sky-700';
    const label = action === 'stock_removed' ? 'Stock removed' : action === 'stock_added' ? 'Stock added' : 'New resource';

    return <span className={`whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ${style}`}>{label}</span>;
}
