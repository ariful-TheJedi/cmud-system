import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { BriefcaseBusiness, Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Assignment = { branch: string; department: string };
type Staff = { id: number; code: string; name: string; phone: string; email: string; designation: string; joined: string; status: string; address: string; notes: string; assignments: Assignment[] };
const branches = ['Main campus', 'Uttara campus'];
const departments = ['Administration', 'Finance', 'Academic', 'Operations'];
const statuses = ['Active', 'On leave', 'Inactive'];
const samples: Staff[] = [
    { id: 1, code: 'STF-001', name: 'Amina Rahman', phone: '01700000001', email: 'amina@example.test', designation: 'Administrator', joined: '2026-01-05', status: 'Active', address: 'Dhaka', notes: '', assignments: [{ branch: 'Main campus', department: 'Administration' }] },
    { id: 2, code: 'STF-002', name: 'Rafiq Hasan', phone: '01700000002', email: 'rafiq@example.test', designation: 'Finance officer', joined: '2026-02-10', status: 'Active', address: 'Dhaka', notes: '', assignments: [{ branch: 'Main campus', department: 'Finance' }, { branch: 'Uttara campus', department: 'Finance' }] },
    { id: 3, code: 'STF-003', name: 'Nabila Karim', phone: '01700000003', email: 'nabila@example.test', designation: 'Coordinator', joined: '2026-03-01', status: 'On leave', address: 'Dhaka', notes: '', assignments: [] },
];
const empty = { code: '', name: '', phone: '', email: '', designation: '', joined: '', status: 'Active', address: '', notes: '' };
const field = 'mt-2 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm focus:outline-teal-600';
const primary = 'rounded-xl bg-[#17375C] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#254878]';

export default function StaffPage() {
    const [staff, setStaff] = useState(samples);
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState('All');
    const [branch, setBranch] = useState('All');
    const [dialog, setDialog] = useState<'create' | 'edit' | 'assign' | null>(null);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [form, setForm] = useState(empty);
    const [assignment, setAssignment] = useState({ branch: '', department: '' });
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const selected = staff.find((item) => item.id === selectedId);
    const visible = staff.filter((item) => `${item.name} ${item.code} ${item.phone} ${item.email} ${item.designation}`.toLowerCase().includes(query.trim().toLowerCase()) && (status === 'All' || item.status === status) && (branch === 'All' || item.assignments.some((value) => value.branch === branch)));

    function open(action: 'create' | 'edit' | 'assign', item?: Staff) {
        setDialog(action); setSelectedId(item?.id ?? null); setForm(item ? { ...item } : { ...empty }); setAssignment({ branch: '', department: '' }); setError('');
    }

    function save(event: FormEvent) {
        event.preventDefault();
        if (dialog === 'assign' && selected) {
            if (!branches.includes(assignment.branch) || !departments.includes(assignment.department)) { setError('Choose a branch and department.'); return; }
            if (selected.assignments.some((item) => item.branch === assignment.branch && item.department === assignment.department)) { setError('This assignment already exists.'); return; }
            setStaff((current) => current.map((item) => item.id === selected.id ? { ...item, assignments: [...item.assignments, { ...assignment }] } : item));
            setAssignment({ branch: '', department: '' }); setError(''); setNotice('Demo assignment added.'); return;
        }
        const data = Object.fromEntries(Object.entries(form).filter(([key]) => key in empty).map(([key, value]) => [key, typeof value === 'string' ? value.trim() : value])) as typeof empty;
        if (!data.code || !data.name || !data.phone || !data.designation || !data.joined) { setError('Complete all required staff details.'); return; }
        if (staff.some((item) => item.id !== selectedId && item.code.toLowerCase() === data.code.toLowerCase())) { setError('Staff code must be unique.'); return; }
        if (dialog === 'edit' && selected) setStaff((current) => current.map((item) => item.id === selected.id ? { ...item, ...data } : item));
        else setStaff((current) => [...current, { ...data, id: Math.max(0, ...current.map((item) => item.id)) + 1, assignments: [] }]);
        setDialog(null); setNotice('Demo staff record saved.');
    }

    function removeAssignment(index: number) {
        if (!selected) return;
        setStaff((current) => current.map((item) => item.id === selected.id ? { ...item, assignments: item.assignments.filter((_, position) => position !== index) } : item));
        setError(''); setNotice('Demo assignment removed.');
    }

    return <AppLayout breadcrumbs={[{ title: 'Staff', href: '/staff' }]}>
        <Head title="Staff" />
        <main className="min-h-screen bg-[#F7F9FC] px-4 py-6 text-slate-800 sm:px-7 lg:px-10"><div className="mx-auto max-w-7xl space-y-6">
            <header className="flex flex-wrap items-center justify-between gap-5 rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-6 text-white sm:p-8"><div><p className="flex items-center gap-2 text-xs font-semibold tracking-widest text-cyan-100 uppercase"><BriefcaseBusiness size={16} /> Staff management</p><h1 className="mt-3 text-3xl font-semibold">Staff</h1><p className="mt-2 text-sm text-cyan-50">Manage staff details and branch and department assignments.</p><p className="mt-4 text-xs text-cyan-100">Demo workspace · Sample data · Changes reset when you refresh or leave</p></div><button type="button" onClick={() => open('create')} className="flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-[#17375C]"><Plus size={17} /> Add staff</button></header>
            {notice && <p role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{notice}</p>}
            <section aria-label="Staff filters" className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-3"><label className="text-sm font-medium">Search<input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Name, staff code, phone, or designation" className={field} /></label><label className="text-sm font-medium">Status<select value={status} onChange={(event) => setStatus(event.target.value)} className={field}><option>All</option>{statuses.map((value) => <option key={value}>{value}</option>)}</select></label><label className="text-sm font-medium">Branch<select value={branch} onChange={(event) => setBranch(event.target.value)} className={field}><option>All</option>{branches.map((value) => <option key={value}>{value}</option>)}</select></label></section>
            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white" aria-labelledby="staff-list"><h2 id="staff-list" className="border-b border-slate-100 px-6 py-5 font-semibold text-[#17375C]">Staff directory <span className="ml-2 text-sm font-normal text-slate-500">{visible.length} records</span></h2><div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-slate-50 text-slate-500"><tr>{['Staff', 'Contact', 'Designation', 'Assignments', 'Status', 'Actions'].map((label) => <th key={label} className="px-5 py-3 font-medium">{label}</th>)}</tr></thead><tbody className="divide-y divide-slate-100">{visible.map((item) => <tr key={item.id}><td className="px-5 py-4 font-medium">{item.name}<p className="mt-1 text-xs text-slate-500">{item.code}</p></td><td className="px-5 py-4">{item.phone}<p className="mt-1 text-xs text-slate-500">{item.email || 'No email'}</p></td><td className="px-5 py-4">{item.designation}</td><td className="px-5 py-4">{item.assignments.length ? item.assignments.map((value) => <p key={`${value.branch}-${value.department}`} className="py-1 text-xs">{value.branch} · {value.department}</p>) : <span className="text-slate-500">Unassigned</span>}</td><td className="whitespace-nowrap px-5 py-4">{item.status}</td><td className="px-5 py-4"><div className="flex gap-2"><button type="button" onClick={() => open('edit', item)} aria-label={`Edit ${item.name}`} className="rounded-lg border border-slate-200 px-3 py-2">Edit</button><button type="button" onClick={() => open('assign', item)} aria-label={`Manage assignments for ${item.name}`} className="rounded-lg border border-teal-200 bg-teal-50 px-3 py-2 text-teal-800">Assignments</button></div></td></tr>)}{!visible.length && <tr><td colSpan={6} className="px-5 py-12 text-center text-slate-500">No staff match your filters.</td></tr>}</tbody></table></div></section>
        </div></main>
        <Dialog open={dialog !== null} onOpenChange={(value) => { if (!value) setDialog(null); }}><DialogContent className="max-h-[85svh] overflow-y-auto sm:max-w-2xl"><DialogHeader><DialogTitle>{dialog === 'assign' ? `Assignments · ${selected?.name ?? ''}` : dialog === 'edit' ? 'Update staff' : 'Add staff'}</DialogTitle><DialogDescription>Demo only. Changes remain until you refresh or leave this page.</DialogDescription></DialogHeader>
            {dialog === 'assign' && selected && <section aria-label="Current assignments" className="space-y-3"><h3 className="text-sm font-semibold">Current assignments</h3>{selected.assignments.map((item, index) => <div key={`${item.branch}-${item.department}`} className="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-3 text-sm"><span>{item.branch} · {item.department}</span><button type="button" onClick={() => removeAssignment(index)} aria-label={`Remove ${item.branch}, ${item.department} assignment`} className="text-rose-700 underline">Remove</button></div>)}{!selected.assignments.length && <p className="text-sm text-slate-500">No assignments yet.</p>}</section>}
            <form onSubmit={save} className="space-y-4">{dialog === 'assign' ? <div className="grid gap-4 sm:grid-cols-2"><label className="text-sm font-medium">Branch<select required value={assignment.branch} onChange={(event) => setAssignment({ ...assignment, branch: event.target.value })} className={field}><option value="">Choose branch</option>{branches.map((value) => <option key={value}>{value}</option>)}</select></label><label className="text-sm font-medium">Department<select required value={assignment.department} onChange={(event) => setAssignment({ ...assignment, department: event.target.value })} className={field}><option value="">Choose department</option>{departments.map((value) => <option key={value}>{value}</option>)}</select></label></div> : <div className="grid gap-4 sm:grid-cols-2">{([
                ['code', 'Staff code', 'text', true], ['name', 'Full name', 'text', true], ['phone', 'Phone', 'tel', true], ['email', 'Email', 'email', false], ['designation', 'Designation', 'text', true], ['joined', 'Joining date', 'date', true], ['address', 'Address', 'text', false], ['notes', 'Notes', 'text', false],
            ] as const).map(([name, label, type, required]) => <label key={name} className="text-sm font-medium">{label}{required && ' *'}<input type={type} required={required} value={form[name]} onChange={(event) => setForm({ ...form, [name]: event.target.value })} className={field} /></label>)}<label className="text-sm font-medium">Employment status<select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })} className={field}>{statuses.map((value) => <option key={value}>{value}</option>)}</select></label></div>}
                {error && <p role="alert" className="text-sm text-rose-700">{error}</p>}
                <div className="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" onClick={() => setDialog(null)} className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm">{dialog === 'assign' ? 'Done' : 'Cancel'}</button><button type="submit" className={primary}>{dialog === 'assign' ? 'Add assignment' : 'Save staff'}</button></div>
            </form>
        </DialogContent></Dialog>
    </AppLayout>;
}
