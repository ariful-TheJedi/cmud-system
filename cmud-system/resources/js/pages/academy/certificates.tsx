import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Award, CheckCircle2, Eye, FilePlus2, History, RotateCcw, Search, ShieldX } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

type Student = { id: string; name: string; batch: string; course: string; eligible: boolean };
type CertificateStatus = 'issued' | 'collected' | 'revoked';
type CertificateEvent = { action: string; date: string; reason: string; reference: string };
type Certificate = { id: number; studentId: string; number: string; type: string; issuedOn: string; status: CertificateStatus; reissues: number; history: CertificateEvent[] };
type Action = 'issue' | 'reissue' | 'collect' | 'revoke' | 'view';
type Editor = { action: Action; certificateId: number | null };

// Local fixtures preview the workflow; no database records or API requests are used.
const students: Student[] = [
    { id: 'STU-1001', name: 'Ayesha Rahman', batch: 'CMUD · Batch 01', course: 'Certificate in Medical Ultrasound', eligible: true },
    { id: 'STU-1002', name: 'Tanvir Ahmed', batch: 'CMUD · Batch 01', course: 'Certificate in Medical Ultrasound', eligible: true },
    { id: 'STU-1003', name: 'Nusrat Jahan', batch: 'CMUD · Batch 01', course: 'Certificate in Medical Ultrasound', eligible: false },
    { id: 'STU-2001', name: 'Mehedi Hasan', batch: 'CMUD · Batch 02', course: 'Certificate in Medical Ultrasound', eligible: true },
    { id: 'STU-3001', name: 'Sadia Akter', batch: 'DMUD · Batch 01', course: 'Diploma in Medical Ultrasound', eligible: true },
];
const initialCertificates: Certificate[] = [
    { id: 1, studentId: 'STU-1001', number: 'CMUD-DEMO-0001', type: 'Course completion', issuedOn: '2026-11-12', status: 'collected', reissues: 0, history: [
        { action: 'Issued', date: '2026-11-12', reason: 'Original certificate prepared.', reference: 'CMUD-DEMO-0001' },
        { action: 'Collected', date: '2026-11-15', reason: 'Received by the student.', reference: 'CMUD-DEMO-0001' },
    ] },
    { id: 2, studentId: 'STU-1002', number: 'CMUD-DEMO-0002', type: 'Course completion', issuedOn: '2026-11-14', status: 'issued', reissues: 1, history: [
        { action: 'Issued', date: '2026-11-14', reason: 'Original certificate prepared.', reference: 'CMUD-DEMO-0002' },
        { action: 'Reissued', date: '2026-11-20', reason: 'Replacement for a damaged certificate.', reference: 'CMUD-DEMO-0002-R1' },
    ] },
];
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Academic', href: '/academy/manage/courses' },
    { title: 'Certificates', href: '/academy/certificates' },
];
const fieldClass = 'mt-1.5 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/10';
const primaryClass = 'inline-flex items-center justify-center gap-2 rounded-xl bg-[#17375C] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#254878] disabled:cursor-not-allowed disabled:opacity-50';
const actionTitles: Record<Action, string> = { issue: 'Issue certificate', reissue: 'Reissue certificate', collect: 'Record collection', revoke: 'Revoke certificate', view: 'Certificate details' };

function latestReference(certificate: Certificate) {
    return certificate.reissues ? `${certificate.number}-R${certificate.reissues}` : certificate.number;
}

export default function Certificates() {
    const [certificates, setCertificates] = useState(initialCertificates);
    const [query, setQuery] = useState('');
    const [batch, setBatch] = useState('all');
    const [status, setStatus] = useState('all');
    const [editor, setEditor] = useState<Editor | null>(null);
    const [studentId, setStudentId] = useState('');
    const [type, setType] = useState('Course completion');
    const [date, setDate] = useState('');
    const [reason, setReason] = useState('');
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const selected = certificates.find((certificate) => certificate.id === editor?.certificateId);
    const selectedStudent = students.find((student) => student.id === (selected?.studentId || studentId));
    const matchingStudents = students.filter((student) => (batch === 'all' || student.batch === batch) && `${student.id} ${student.name}`.toLowerCase().includes(query.trim().toLowerCase()));
    const visibleCertificates = certificates.filter((certificate) => {
        const student = students.find((item) => item.id === certificate.studentId)!;
        return (batch === 'all' || student.batch === batch) && (status === 'all' || certificate.status === status) && `${student.name} ${student.id} ${latestReference(certificate)}`.toLowerCase().includes(query.trim().toLowerCase());
    });
    const canIssue = selectedStudent?.eligible && !certificates.some((certificate) => certificate.studentId === studentId && certificate.type === type);

    function open(action: Action, certificateId: number | null = null, initialStudentId = '') {
        setEditor({ action, certificateId });
        setStudentId(initialStudentId);
        setType('Course completion');
        setDate('');
        setReason('');
        setError('');
    }

    function save(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!editor || editor.action === 'view') return;
        if (!date || (editor.action !== 'issue' && !reason.trim())) {
            setError('Enter the date and a reason or collection note.');
            return;
        }
        if (editor.action === 'issue') {
            if (!canIssue) { setError('Choose an eligible student without an existing certificate of this type. Use the existing record for reissues.'); return; }
            const id = Math.max(0, ...certificates.map((certificate) => certificate.id)) + 1;
            const number = `CMUD-DEMO-${String(id).padStart(4, '0')}`;
            setCertificates((current) => [...current, { id, studentId, number, type, issuedOn: date, status: 'issued', reissues: 0, history: [{ action: 'Issued', date, reason: reason.trim() || 'Original certificate prepared.', reference: number }] }]);
        } else if (selected) {
            const lastDate = selected.history[selected.history.length - 1].date;
            if (date < lastDate) { setError(`Choose a date on or after the last action (${lastDate}).`); return; }
            const action = editor.action;
            // Preserve the original certificate and append every lifecycle action to its history.
            setCertificates((current) => current.map((certificate) => {
                if (certificate.id !== selected.id) return certificate;
                const reissues = certificate.reissues + (action === 'reissue' ? 1 : 0);
                const reference = reissues ? `${certificate.number}-R${reissues}` : certificate.number;
                return { ...certificate, reissues, status: action === 'revoke' ? 'revoked' : action === 'collect' ? 'collected' : 'issued', history: [...certificate.history, { action: action === 'reissue' ? 'Reissued' : action === 'collect' ? 'Collected' : 'Revoked', date, reason: reason.trim(), reference }] };
            }));
        }
        setNotice('Demo certificate register updated. Changes reset when you refresh or leave this page.');
        setEditor(null);
    }

    return <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Certificates" />
        <main className="min-h-screen bg-[#F7F9FC] px-4 py-6 text-slate-800 sm:px-7 lg:px-10">
            <div className="mx-auto max-w-7xl space-y-6">
                <header className="flex flex-wrap items-center justify-between gap-5 rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-6 text-white sm:p-8">
                    <div><p className="flex items-center gap-2 text-xs font-semibold tracking-[0.16em] text-cyan-100 uppercase"><Award size={16} /> Academic</p><h1 className="mt-3 text-3xl font-semibold tracking-tight">Certificates</h1><p className="mt-2 max-w-xl text-sm text-cyan-50/85">Manage certificate issuance, replacements, and student collection.</p><p className="mt-4 text-xs text-cyan-100">Demo workspace · Sample data · Changes are temporary</p></div>
                    <button type="button" onClick={() => open('issue')} className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-[#17375C] hover:bg-cyan-50"><FilePlus2 size={18} /> Issue certificate</button>
                </header>

                <div className="grid gap-4 sm:grid-cols-3">{[
                    { title: 'Total certificates', value: certificates.length, icon: Award },
                    { title: 'Awaiting collection', value: certificates.filter((certificate) => certificate.status === 'issued').length, icon: FilePlus2 },
                    { title: 'Reissue actions', value: certificates.reduce((total, certificate) => total + certificate.reissues, 0), icon: RotateCcw },
                ].map(({ title, value, icon: Icon }) => <div key={title} className="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5"><span className="rounded-xl bg-teal-50 p-3 text-teal-700"><Icon size={21} /></span><div><p className="text-xs text-slate-500">{title}</p><p className="mt-1 text-2xl font-semibold text-[#17375C]">{value}</p></div></div>)}</div>

                {notice && <p role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{notice}</p>}
                <section aria-label="Search certificates" className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-3">
                    <label className="text-xs font-medium text-slate-600">Search<div className="relative"><Search size={16} className="absolute top-5 left-3 text-slate-400" /><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Name, student ID, or certificate no." className={`${fieldClass} pl-9`} /></div></label>
                    <label className="text-xs font-medium text-slate-600">Batch<select value={batch} onChange={(event) => setBatch(event.target.value)} className={fieldClass}><option value="all">All batches</option>{[...new Set(students.map((student) => student.batch))].map((value) => <option key={value}>{value}</option>)}</select></label>
                    <label className="text-xs font-medium text-slate-600">Certificate status<select value={status} onChange={(event) => setStatus(event.target.value)} className={fieldClass}><option value="all">All statuses</option><option value="issued">Awaiting collection</option><option value="collected">Collected</option><option value="revoked">Revoked</option></select></label>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white" aria-labelledby="register-title">
                    <div className="border-b border-slate-100 px-6 py-5"><h2 id="register-title" className="font-semibold text-[#17375C]">Certificate register <span className="ml-2 text-sm font-normal text-slate-400">{visibleCertificates.length} records</span></h2></div>
                    <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-slate-50 text-xs text-slate-500"><tr>{['Student', 'Certificate', 'Original issue', 'Status', 'Actions'].map((label) => <th key={label} className="whitespace-nowrap px-5 py-3 font-medium">{label}</th>)}</tr></thead><tbody className="divide-y divide-slate-100">
                        {visibleCertificates.map((certificate) => { const student = students.find((item) => item.id === certificate.studentId)!; return <tr key={certificate.id}>
                            <td className="px-5 py-4"><p className="font-semibold text-[#17375C]">{student.name}</p><p className="mt-1 text-xs text-slate-500">{student.id} · {student.batch}</p></td>
                            <td className="px-5 py-4"><p className="whitespace-nowrap font-mono text-xs">{latestReference(certificate)}</p><p className="mt-1 text-xs text-slate-500">{certificate.type}{certificate.reissues > 0 && ` · ${certificate.reissues} reissue(s)`}</p></td>
                            <td className="whitespace-nowrap px-5 py-4 text-xs text-slate-500">{certificate.issuedOn}</td><td className="px-5 py-4"><StatusBadge status={certificate.status} /></td>
                            <td className="px-5 py-4"><div className="flex flex-wrap gap-2">
                                <ActionButton label="View history" icon={Eye} onClick={() => open('view', certificate.id)} />
                                {certificate.status !== 'revoked' && <ActionButton label="Reissue" icon={RotateCcw} onClick={() => open('reissue', certificate.id)} />}
                                {certificate.status === 'issued' && <ActionButton label="Collect" icon={CheckCircle2} onClick={() => open('collect', certificate.id)} />}
                                {certificate.status !== 'revoked' && <ActionButton label="Revoke" icon={ShieldX} onClick={() => open('revoke', certificate.id)} />}
                            </div></td>
                        </tr>; })}
                        {!visibleCertificates.length && <tr><td colSpan={5} className="px-6 py-12 text-center text-slate-500">No certificates match your filters.</td></tr>}
                    </tbody></table></div>
                </section>

                <section aria-labelledby="students-title" className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 id="students-title" className="font-semibold text-[#17375C]">Student lookup</h2><p className="mt-1 text-xs text-slate-500">Find students by name, ID, and batch using the filters above. Eligibility below is sample data for this demo.</p>
                    <div className="mt-4 divide-y divide-slate-100">{matchingStudents.map((student) => {
                        const existing = certificates.find((certificate) => certificate.studentId === student.id && certificate.type === 'Course completion');
                        return <div key={student.id} className="flex flex-wrap items-center justify-between gap-3 py-4"><div><p className="text-sm font-medium text-[#17375C]">{student.name} <span className="ml-2 text-xs font-normal text-slate-400">{student.id}</span></p><p className="mt-1 text-xs text-slate-500">{student.batch} · {student.course}</p></div><div className="flex items-center gap-3"><span className={`text-xs ${student.eligible ? 'text-teal-700' : 'text-slate-400'}`}>{student.eligible ? 'Eligible' : 'Completion pending'}</span>{existing ? <button type="button" onClick={() => open('view', existing.id)} className="text-xs font-semibold text-[#17375C]">View certificate</button> : <button type="button" disabled={!student.eligible} onClick={() => open('issue', null, student.id)} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-[#17375C] hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">Issue certificate</button>}</div></div>;
                    })}{!matchingStudents.length && <p className="py-6 text-sm text-slate-500">No students found. Try a student name or ID, or clear the search.</p>}</div>
                </section>
            </div>
        </main>

        <Dialog open={editor !== null} onOpenChange={(open) => { if (!open) setEditor(null); }}>
            <DialogContent className="max-h-[85svh] overflow-y-auto sm:max-w-xl">
                <DialogHeader><DialogTitle>{editor ? actionTitles[editor.action] : 'Certificates'}</DialogTitle><DialogDescription>{editor?.action === 'view' ? 'Certificate details and the complete demo action history.' : 'Update this demo register. Changes are temporary and no real certificate is generated.'}</DialogDescription></DialogHeader>
                {selected && <div className="rounded-xl bg-slate-50 p-4"><div className="flex flex-wrap items-center justify-between gap-2"><p className="font-mono text-sm font-semibold text-[#17375C]">{latestReference(selected)}</p><StatusBadge status={selected.status} /></div><p className="mt-2 text-sm font-medium">{selectedStudent?.name}</p><p className="mt-1 text-xs text-slate-500">{selectedStudent?.id} · {selectedStudent?.batch}</p><p className="mt-1 text-xs text-slate-500">{selectedStudent?.course} · {selected.type}</p></div>}
                {editor?.action === 'view' && selected ? <div><h3 className="mb-4 flex items-center gap-2 text-sm font-semibold text-[#17375C]"><History size={16} /> Issuance history</h3><ol className="space-y-4 border-l border-slate-200 pl-4">{selected.history.map((event, index) => <li key={index}><p className="text-sm font-medium">{event.action}<span className="ml-2 text-xs font-normal text-slate-400">{event.date}</span></p><p className="mt-1 font-mono text-xs text-slate-500">{event.reference}</p><p className="mt-1 text-sm text-slate-600">{event.reason}</p></li>)}</ol></div> : <form onSubmit={save} className="space-y-4">
                    {editor?.action === 'issue' && <>
                        <label className="block text-xs font-medium text-slate-600">Student<select required value={studentId} onChange={(event) => { setStudentId(event.target.value); setError(''); }} className={fieldClass}><option value="">Select student</option>{students.map((student) => <option key={student.id} value={student.id} disabled={!student.eligible}>{student.id} · {student.name} · {student.batch}{!student.eligible ? ' · Completion pending' : ''}</option>)}</select></label>
                        {selectedStudent && <p className="text-xs text-slate-500">{selectedStudent.course}</p>}
                        <label className="block text-xs font-medium text-slate-600">Certificate type<select value={type} onChange={(event) => { setType(event.target.value); setError(''); }} className={fieldClass}><option>Course completion</option><option>Provisional</option></select></label>
                        {selectedStudent && !canIssue && <p className="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">A certificate of this type already exists, or the student is not eligible. Use the register to manage existing certificates.</p>}
                    </>}
                    {editor?.action === 'reissue' && <p className="text-xs leading-5 text-slate-500">A replacement reference will be added. The original number and all previous actions remain in the history. Collection will need to be recorded for the new copy.</p>}
                    {editor?.action === 'revoke' && <p className="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">This marks the certificate as revoked and blocks further collection or reissue in this demo.</p>}
                    <label className="block text-xs font-medium text-slate-600">{editor?.action === 'collect' ? 'Collection date' : editor?.action === 'revoke' ? 'Revocation date' : 'Issue date'}<input type="date" required min={selected?.history[selected.history.length - 1].date} value={date} onChange={(event) => setDate(event.target.value)} className={fieldClass} /></label>
                    <label className="block text-xs font-medium text-slate-600">{editor?.action === 'issue' ? 'Remarks (optional)' : editor?.action === 'collect' ? 'Received by / collection notes' : 'Reason'}<textarea required={editor?.action !== 'issue'} rows={3} value={reason} onChange={(event) => setReason(event.target.value)} className={`${fieldClass} h-auto py-3`} placeholder={editor?.action === 'reissue' ? 'e.g. Lost original, damaged copy, or corrected details' : 'Add relevant details'} /></label>
                    {error && <p role="alert" className="text-sm text-rose-700">{error}</p>}
                    <div className="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" onClick={() => setEditor(null)} className="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-600">Cancel</button><button type="submit" disabled={editor?.action === 'issue' && !canIssue} className={primaryClass}>{editor?.action === 'revoke' ? 'Confirm revocation' : 'Save demo entry'}</button></div>
                </form>}
            </DialogContent>
        </Dialog>
    </AppLayout>;
}

function StatusBadge({ status }: { status: CertificateStatus }) {
    const colors = { issued: 'bg-sky-50 text-sky-700', collected: 'bg-emerald-50 text-emerald-700', revoked: 'bg-rose-50 text-rose-700' };
    return <span className={`inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ${colors[status]}`}>{status === 'issued' ? 'Awaiting collection' : status === 'collected' ? 'Collected' : 'Revoked'}</span>;
}

function ActionButton({ label, icon: Icon, onClick }: { label: string; icon: typeof Eye; onClick: () => void }) {
    return <button type="button" onClick={onClick} className="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border border-slate-200 px-2.5 py-2 text-xs font-medium text-[#17375C] hover:border-teal-200 hover:bg-teal-50"><Icon size={14} />{label}</button>;
}
