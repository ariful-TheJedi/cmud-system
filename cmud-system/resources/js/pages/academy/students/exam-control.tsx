import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { CheckCircle2, ClipboardList, FileCheck2, Search, Users } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Student = { id: string; name: string; batchId: string };
type Attendance = 'participated' | 'absent' | 'not-recorded';
type Result = 'pending' | 'passed' | 'failed' | 'retake' | 'withheld';
type ExamEntry = {
    studentId: string;
    module: string;
    exam: string;
    date: string;
    attempt: number;
    attendance: Attendance;
    result: Result;
    marks: string;
    total: string;
    notes: string;
};

// Demo fixtures are deliberately separate from live Academy data and API calls.
const batches = [
    { id: 'B-01', name: 'CMUD · Batch 01', course: 'Certificate in Medical Ultrasound', modules: ['Abdominal ultrasound', 'Obstetric ultrasound', 'Clinical practice'] },
    { id: 'B-02', name: 'CMUD · Batch 02', course: 'Certificate in Medical Ultrasound', modules: ['Abdominal ultrasound', 'Obstetric ultrasound', 'Clinical practice'] },
    { id: 'B-03', name: 'DMUD · Batch 01', course: 'Diploma in Medical Ultrasound', modules: ['Advanced sonography', 'Doppler studies', 'Clinical assessment'] },
];
const students: Student[] = [
    { id: 'STU-1001', name: 'Ayesha Rahman', batchId: 'B-01' },
    { id: 'STU-1002', name: 'Tanvir Ahmed', batchId: 'B-01' },
    { id: 'STU-1003', name: 'Nusrat Jahan', batchId: 'B-01' },
    { id: 'STU-2001', name: 'Mehedi Hasan', batchId: 'B-02' },
    { id: 'STU-2002', name: 'Farhana Islam', batchId: 'B-02' },
    { id: 'STU-3001', name: 'Sadia Akter', batchId: 'B-03' },
];
const initialEntries: ExamEntry[] = [
    { studentId: 'STU-1001', module: 'Abdominal ultrasound', exam: 'Module final', date: '2026-11-15', attempt: 1, attendance: 'participated', result: 'passed', marks: '78', total: '100', notes: '' },
    { studentId: 'STU-1002', module: 'Abdominal ultrasound', exam: 'Module final', date: '2026-11-15', attempt: 1, attendance: 'participated', result: 'retake', marks: '38', total: '100', notes: 'Retake required.' },
];
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Academic management', href: '/academy/manage/courses' },
    { title: 'Exam Control', href: '/academy/students/exam-control' },
];
const inputClass = 'mt-1.5 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/10';
const resultLabels: Record<Result, string> = { pending: 'Pending', passed: 'Passed', failed: 'Failed', retake: 'Retake', withheld: 'Withheld' };
const attendanceLabels: Record<Attendance, string> = { participated: 'Participated', absent: 'Absent', 'not-recorded': 'Not recorded' };

function emptyEntry(student: Student): ExamEntry {
    return { studentId: student.id, module: batches.find((batch) => batch.id === student.batchId)!.modules[0], exam: 'Module final', date: '', attempt: 1, attendance: 'participated', result: 'pending', marks: '', total: '100', notes: '' };
}

export default function ExamControl() {
    const [batchId, setBatchId] = useState('B-01');
    const [studentId, setStudentId] = useState('');
    const [filters, setFilters] = useState({ batchId: 'B-01', studentId: '' });
    const [entries, setEntries] = useState<ExamEntry[]>(initialEntries);
    const [selectedStudent, setSelectedStudent] = useState<Student | null>(null);
    const [form, setForm] = useState<ExamEntry | null>(null);
    const [editingIndex, setEditingIndex] = useState<number | null>(null);
    const [notice, setNotice] = useState('');
    const [error, setError] = useState('');
    const visibleStudents = students.filter((student) => student.batchId === filters.batchId && student.id.toLowerCase().includes(filters.studentId.trim().toLowerCase()));
    const visibleIds = new Set(visibleStudents.map((student) => student.id));
    const visibleEntries = entries.filter((entry) => visibleIds.has(entry.studentId));
    const selectedBatch = batches.find((batch) => batch.id === selectedStudent?.batchId);

    function openEntry(student: Student, index: number | null = null) {
        setSelectedStudent(student);
        setEditingIndex(index);
        setForm(index === null ? emptyEntry(student) : { ...entries[index] });
        setError('');
        setNotice('');
    }

    function update<K extends keyof ExamEntry>(key: K, value: ExamEntry[K]) {
        setForm((current) => current ? { ...current, [key]: value } : current);
        setError('');
        setNotice('');
    }

    function saveEntry(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!form) return;
        if (form.attendance !== 'participated' && (form.result !== 'pending' || form.marks !== '')) {
            setError('Record participation before entering marks or a result.');
            return;
        }
        if (form.marks !== '' && (Number(form.marks) < 0 || Number(form.marks) > Number(form.total))) {
            setError('Marks must be between zero and the total marks.');
            return;
        }
        if (entries.some((entry, index) => index !== editingIndex && entry.studentId === form.studentId && entry.module === form.module && entry.exam === form.exam && entry.attempt === form.attempt)) {
            setError('This exam attempt already exists. Edit the existing entry or choose another attempt number.');
            return;
        }
        setEntries((current) => editingIndex === null ? [...current, { ...form }] : current.map((entry, index) => index === editingIndex ? { ...form } : entry));
        setNotice(`Exam entry ${editingIndex === null ? 'added' : 'updated'} for ${selectedStudent?.name}. Demo changes last until this page is refreshed or left.`);
        setForm(null);
        setSelectedStudent(null);
        setEditingIndex(null);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Exam Control" />
            <main className="min-h-screen bg-[#F7F9FC] px-4 py-6 text-slate-800 sm:px-7 lg:px-10">
                <div className="mx-auto max-w-7xl space-y-6">
                    <header className="rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-6 text-white shadow-lg sm:p-8">
                        <div className="flex items-center gap-2 text-xs font-semibold tracking-[0.16em] text-cyan-100 uppercase"><FileCheck2 size={16} /> Academic management</div>
                        <h1 className="mt-3 text-3xl font-semibold tracking-tight">Exam Control</h1>
                        <p className="mt-2 max-w-2xl text-sm text-cyan-50/85">Find a student, record exam participation, and manage results and retake attempts.</p>
                        <span className="mt-5 inline-flex rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-medium">Demo workspace · Sample students · Changes are temporary</span>
                    </header>

                    <div className="grid gap-4 sm:grid-cols-3">
                        {[
                            { label: 'Students in search', value: visibleStudents.length, icon: Users },
                            { label: 'Participation entries', value: visibleEntries.filter((entry) => entry.attendance === 'participated').length, icon: ClipboardList },
                            { label: 'Passed entries', value: visibleEntries.filter((entry) => entry.result === 'passed').length, icon: CheckCircle2 },
                        ].map(({ label, value, icon: Icon }) => <div key={label} className="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5"><span className="rounded-xl bg-teal-50 p-3 text-teal-700"><Icon size={21} /></span><div><p className="text-xs text-slate-500">{label}</p><p className="mt-1 text-2xl font-semibold text-[#17375C]">{value}</p></div></div>)}
                    </div>

                    <section aria-labelledby="student-search-title" className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <h2 id="student-search-title" className="text-lg font-semibold text-[#17375C]">Find students</h2>
                        <p className="mt-1 text-sm text-slate-500">Choose a batch, then enter a student ID or leave it blank to see the batch roster.</p>
                        <form onSubmit={(event) => { event.preventDefault(); setFilters({ batchId, studentId }); setForm(null); setSelectedStudent(null); setNotice(''); setError(''); }} className="mt-5 grid items-end gap-4 sm:grid-cols-[1fr_1fr_auto]">
                            <label className="text-xs font-medium text-slate-600">Batch<select value={batchId} onChange={(event) => setBatchId(event.target.value)} className={inputClass}>{batches.map((batch) => <option key={batch.id} value={batch.id}>{batch.name}</option>)}</select></label>
                            <label className="text-xs font-medium text-slate-600">Student ID<input value={studentId} onChange={(event) => setStudentId(event.target.value)} placeholder="e.g. STU-1001" className={inputClass} /></label>
                            <button type="submit" className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#17375C] px-5 text-sm font-semibold text-white hover:bg-[#254878]"><Search size={16} /> Find students</button>
                        </form>
                        <p className="mt-3 text-xs text-slate-400">Try STU-1001 or STU-1002 in CMUD · Batch 01.</p>
                    </section>

                    {notice && <p role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{notice}</p>}
                    <section aria-labelledby="roster-title" className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <div className="border-b border-slate-100 px-6 py-5"><h2 id="roster-title" className="font-semibold text-[#17375C]">Student roster <span className="ml-2 text-sm font-normal text-slate-400">{visibleStudents.length} found</span></h2></div>
                        <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-slate-50 text-xs text-slate-500"><tr>{['Student', 'Batch / course', 'Exam entries', 'Action'].map((label) => <th key={label} className="px-6 py-3 font-medium">{label}</th>)}</tr></thead><tbody className="divide-y divide-slate-100">
                            {visibleStudents.map((student) => { const batch = batches.find((item) => item.id === student.batchId)!; return <tr key={student.id} className={selectedStudent?.id === student.id ? 'bg-teal-50/50' : ''}><td className="px-6 py-4"><p className="font-semibold text-[#17375C]">{student.name}</p><p className="mt-1 font-mono text-xs text-slate-500">{student.id}</p></td><td className="px-6 py-4"><p>{batch.name}</p><p className="mt-1 text-xs text-slate-500">{batch.course}</p></td><td className="px-6 py-4">{entries.filter((entry) => entry.studentId === student.id).length}</td><td className="px-6 py-4"><button type="button" onClick={() => openEntry(student)} className="whitespace-nowrap rounded-lg border border-teal-200 bg-teal-50 px-3 py-2 text-xs font-semibold text-teal-800 hover:bg-teal-100">Record exam<span className="sr-only"> for {student.name}</span></button></td></tr>; })}
                            {!visibleStudents.length && <tr><td colSpan={4} className="px-6 py-12 text-center text-slate-500">No students match this batch and student ID. Check the ID or choose another batch.</td></tr>}
                        </tbody></table></div>
                    </section>

                    {form && selectedStudent && selectedBatch && <section aria-labelledby="exam-entry-title" className="rounded-2xl border border-teal-200 bg-white p-5 shadow-sm sm:p-6">
                        <h2 id="exam-entry-title" className="text-lg font-semibold text-[#17375C]">{editingIndex === null ? 'Record exam' : 'Edit exam entry'} · {selectedStudent.name}</h2>
                        <p className="mt-1 text-sm text-slate-500">{selectedStudent.id} · {selectedBatch.name}</p>
                        <form onSubmit={saveEntry} className="mt-5 space-y-5">
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <label className="text-xs font-medium text-slate-600">Module / subject<select value={form.module} onChange={(event) => update('module', event.target.value)} className={inputClass}>{selectedBatch.modules.map((module) => <option key={module}>{module}</option>)}</select></label>
                                <label className="text-xs font-medium text-slate-600">Exam<select value={form.exam} onChange={(event) => update('exam', event.target.value)} className={inputClass}>{['Module final', 'Midterm', 'Practical', 'Viva'].map((exam) => <option key={exam}>{exam}</option>)}</select></label>
                                <label className="text-xs font-medium text-slate-600">Exam date<input type="date" required value={form.date} onChange={(event) => update('date', event.target.value)} className={inputClass} /></label>
                                <label className="text-xs font-medium text-slate-600">Attempt number<input type="number" min="1" step="1" required value={form.attempt} onChange={(event) => update('attempt', Number(event.target.value))} className={inputClass} /></label>
                                <label className="text-xs font-medium text-slate-600">Participation<select value={form.attendance} onChange={(event) => { const attendance = event.target.value as Attendance; setForm({ ...form, attendance, ...(attendance !== 'participated' ? { result: 'pending' as Result, marks: '' } : {}) }); setError(''); }} className={inputClass}>{Object.entries(attendanceLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
                                <label className="text-xs font-medium text-slate-600">Result<select disabled={form.attendance !== 'participated'} value={form.result} onChange={(event) => update('result', event.target.value as Result)} className={`${inputClass} disabled:bg-slate-100 disabled:text-slate-400`}>{Object.entries(resultLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
                                <label className="text-xs font-medium text-slate-600">Marks obtained (optional)<input type="number" min="0" max={form.total} step="0.01" disabled={form.attendance !== 'participated'} value={form.marks} onChange={(event) => update('marks', event.target.value)} className={`${inputClass} disabled:bg-slate-100`} /></label>
                                <label className="text-xs font-medium text-slate-600">Total marks<input type="number" min="1" step="0.01" required value={form.total} onChange={(event) => update('total', event.target.value)} className={inputClass} /></label>
                            </div>
                            <label className="block text-xs font-medium text-slate-600">Remarks<textarea rows={3} value={form.notes} onChange={(event) => update('notes', event.target.value)} placeholder="Add result notes or retake instructions" className={`${inputClass} h-auto py-3`} /></label>
                            <p className="text-xs text-slate-500">Select the result manually. For a retake exam, add a new entry with the next attempt number to keep the previous result.</p>
                            {error && <p role="alert" className="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</p>}
                            <div className="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" onClick={() => { setForm(null); setSelectedStudent(null); setError(''); }} className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600">Cancel</button><button type="submit" className="rounded-xl bg-[#17375C] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#254878]">{editingIndex === null ? 'Save demo entry' : 'Update demo entry'}</button></div>
                        </form>
                    </section>}

                    <section aria-labelledby="exam-register-title" className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <div className="border-b border-slate-100 px-6 py-5"><h2 id="exam-register-title" className="font-semibold text-[#17375C]">Exam register</h2><p className="mt-1 text-xs text-slate-500">Participation and results for students matching your search.</p></div>
                        <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-slate-50 text-xs text-slate-500"><tr>{['Student', 'Exam / module', 'Date', 'Participation', 'Result', 'Marks', 'Action'].map((label) => <th key={label} className="whitespace-nowrap px-5 py-3 font-medium">{label}</th>)}</tr></thead><tbody className="divide-y divide-slate-100">
                            {entries.map((entry, index) => { if (!visibleIds.has(entry.studentId)) return null; const student = students.find((item) => item.id === entry.studentId)!; return <tr key={`${entry.studentId}-${index}`}><td className="px-5 py-4"><p className="whitespace-nowrap font-medium text-[#17375C]">{student.name}</p><p className="mt-1 text-xs text-slate-500">{student.id}</p></td><td className="px-5 py-4"><p className="whitespace-nowrap">{entry.exam} · Attempt {entry.attempt}</p><p className="mt-1 text-xs text-slate-500">{entry.module}</p>{entry.notes && <p className="mt-1 max-w-xs text-xs text-slate-500">{entry.notes}</p>}</td><td className="whitespace-nowrap px-5 py-4 text-xs text-slate-500">{entry.date}</td><td className="px-5 py-4 text-xs">{attendanceLabels[entry.attendance]}</td><td className="px-5 py-4"><ResultBadge result={entry.result} /></td><td className="whitespace-nowrap px-5 py-4 text-xs">{entry.marks === '' ? '—' : `${entry.marks} / ${entry.total}`}</td><td className="px-5 py-4"><button type="button" onClick={() => openEntry(student, index)} className="text-xs font-semibold text-teal-700 hover:underline">Edit<span className="sr-only"> {student.name}, {entry.module}, attempt {entry.attempt}</span></button></td></tr>; })}
                            {!visibleEntries.length && <tr><td colSpan={7} className="px-6 py-12 text-center text-slate-500">No exam entries yet. Choose a student above to record their first exam.</td></tr>}
                        </tbody></table></div>
                    </section>
                </div>
            </main>
        </AppLayout>
    );
}

function ResultBadge({ result }: { result: Result }) {
    const colors: Record<Result, string> = { pending: 'bg-slate-100 text-slate-600', passed: 'bg-emerald-50 text-emerald-700', failed: 'bg-rose-50 text-rose-700', retake: 'bg-amber-50 text-amber-800', withheld: 'bg-violet-50 text-violet-700' };
    return <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${colors[result]}`}>{resultLabels[result]}</span>;
}
