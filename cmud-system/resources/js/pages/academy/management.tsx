import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { BookOpen, BriefcaseBusiness, CalendarDays, Check, ChevronLeft, ChevronRight, Eye, GraduationCap, Layers3, LoaderCircle, Pencil, Plus, Search, Settings2, Trash2, Users, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';

type InstituteOption = { id: number; name: string };
type BranchOption = { id: number; institute_id: number; name: string };
type CourseOption = { id: number; code: string; name: string; institute_id?: number };
type SubjectOption = { id: number; code: string; name: string };
type CourseRecord = CourseOption & { institute_id: number; description: string | null; duration_months: number; completion_period_months: number; course_mode: string; is_active: boolean; institute?: InstituteOption; subjects: SubjectOption[]; batches: BatchRecord[] };
type BatchRecord = { id: number; course_id: number; branch_id: number; code: string; name: string; admission_opens_on?: string | null; admission_closes_on?: string | null; starts_on: string; expected_ends_on: string; actual_ends_on?: string | null; status: string; capacity: number | null; course?: CourseOption; branch?: BranchOption };
type SubjectRecord = SubjectOption & { description: string | null; is_active: boolean; courses: CourseOption[] };
type SubjectAssignment = { subject_id: number; teaching_role: string; is_active: boolean; notes: string };
type TeacherRecord = { id: number; institute_id: number; staff_id: number | null; teacher_code: string; full_name: string; phone: string; email: string | null; qualification: string | null; specialization: string | null; designation: string | null; organization: string | null; bmdc_number: string | null; address: string | null; photo_path: string | null; is_active: boolean; notes: string | null; institute?: InstituteOption; subjects: Array<SubjectOption & { pivot: { teaching_role: string | null; is_active: boolean; notes: string | null } }>; branches: BranchOption[] };
type PageData<T> = { data: T[]; current_page: number; last_page: number; total: number };
type Section = 'courses' | 'batches' | 'subjects' | 'teachers';
type FormState = Record<string, unknown>;

// Keep this route's page code focused on the interactive Academy catalog workspace.
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Academy', href: '/academy/students' },
    { title: 'Management', href: '/academy/manage' },
];

const sections: Array<{ key: Section; label: string; icon: typeof GraduationCap }> = [
    { key: 'courses', label: 'Courses', icon: GraduationCap },
    { key: 'batches', label: 'Batches', icon: CalendarDays },
    { key: 'subjects', label: 'Modules & subjects', icon: BookOpen },
    { key: 'teachers', label: 'Teachers', icon: BriefcaseBusiness },
];

const sectionLabels: Record<Section, string> = {
    courses: 'course',
    batches: 'batch',
    subjects: 'subject or module',
    teachers: 'teacher',
};

async function apiRequest<T>(url: string, init?: RequestInit): Promise<T> {
    const response = await fetch(url, {
        ...init,
        headers: {
            Accept: 'application/json',
            ...(init?.body ? { 'Content-Type': 'application/json' } : {}),
            ...init?.headers,
        },
        credentials: 'same-origin',
    });
    const result = await response.json().catch(() => null) as (T & { message?: string; errors?: Record<string, string[]> }) | null;

    if (!response.ok) {
        const validationMessage = result?.errors ? Object.values(result.errors).flat()[0] : null;
        throw new Error(validationMessage ?? result?.message ?? `Request failed (${response.status}).`);
    }

    return result as T;
}

/** Provides API-shaped defaults for the create dialog matching each resource. */
function createForm(section: Section): FormState {
    switch (section) {
        case 'courses':
            return { institute_id: '', code: '', name: '', description: '', duration_months: 1, completion_period_months: 1, course_mode: 'offline', is_active: true, subject_ids: [] };
        case 'batches':
            return { course_id: '', branch_id: '', code: '', name: '', admission_opens_on: '', admission_closes_on: '', starts_on: '', expected_ends_on: '', capacity: '', status: 'active', notes: '' };
        case 'subjects':
            return { code: '', name: '', description: '', is_active: true, course_ids: [] };
        case 'teachers':
            return { institute_id: '', teacher_code: '', full_name: '', phone: '', email: '', qualification: '', specialization: '', designation: '', organization: '', bmdc_number: '', address: '', is_active: true, notes: '', subject_assignments: [], branch_ids: [] };
    }
}

export default function AcademyManagement({ institutes, branches }: { institutes: InstituteOption[]; branches: BranchOption[] }) {
    const [section, setSection] = useState<Section>('courses');
    const [courses, setCourses] = useState<PageData<CourseRecord>>({ data: [], current_page: 1, last_page: 1, total: 0 });
    const [batches, setBatches] = useState<PageData<BatchRecord>>({ data: [], current_page: 1, last_page: 1, total: 0 });
    const [subjects, setSubjects] = useState<PageData<SubjectRecord>>({ data: [], current_page: 1, last_page: 1, total: 0 });
    const [teachers, setTeachers] = useState<PageData<TeacherRecord>>({ data: [], current_page: 1, last_page: 1, total: 0 });
    const [pages, setPages] = useState<Record<Section, number>>({ courses: 1, batches: 1, subjects: 1, teachers: 1 });
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [query, setQuery] = useState('');
    const [editor, setEditor] = useState<{ section: Section; id: number | null; form: FormState } | null>(null);
    const [viewRecord, setViewRecord] = useState<{ section: Section; record: CourseRecord | BatchRecord | SubjectRecord | TeacherRecord } | null>(null);

    // Keep each tab's server pagination separate so switching sections preserves its place.
    const reload = useCallback(async (target: Section, page: number) => {
        const result = await apiRequest<PageData<CourseRecord | BatchRecord | SubjectRecord | TeacherRecord>>(`/api/v1/academy-catalog/${target}?page=${page}`);
        switch (target) {
            case 'courses': setCourses(result as PageData<CourseRecord>); break;
            case 'batches': setBatches(result as PageData<BatchRecord>); break;
            case 'subjects': setSubjects(result as PageData<SubjectRecord>); break;
            case 'teachers': setTeachers(result as PageData<TeacherRecord>); break;
        }
    }, []);

    useEffect(() => {
        const controller = new AbortController();
        async function loadPage() {
            setLoading(true);
            setError('');
            try {
                const result = await apiRequest<PageData<CourseRecord | BatchRecord | SubjectRecord | TeacherRecord>>(`/api/v1/academy-catalog/${section}?page=${pages[section]}`, { signal: controller.signal });
                switch (section) {
                    case 'courses': setCourses(result as PageData<CourseRecord>); break;
                    case 'batches': setBatches(result as PageData<BatchRecord>); break;
                    case 'subjects': setSubjects(result as PageData<SubjectRecord>); break;
                    case 'teachers': setTeachers(result as PageData<TeacherRecord>); break;
                }
            } catch (requestError) {
                if (requestError instanceof DOMException && requestError.name === 'AbortError') return;
                setError(requestError instanceof Error ? requestError.message : 'Could not load Academy records.');
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }
        void loadPage();
        return () => controller.abort();
    }, [section, pages, reload]);

    const currentRows = useMemo(() => {
        const records: Array<CourseRecord | BatchRecord | SubjectRecord | TeacherRecord> = section === 'courses' ? courses.data
            : section === 'batches' ? batches.data
                : section === 'subjects' ? subjects.data : teachers.data;
        const normalizedQuery = query.trim().toLowerCase();
        if (!normalizedQuery) return records;
        return records.filter((record) => JSON.stringify(record).toLowerCase().includes(normalizedQuery));
    }, [section, courses.data, batches.data, subjects.data, teachers.data, query]);

    const counts: Record<Section, number> = { courses: courses.total, batches: batches.total, subjects: subjects.total, teachers: teachers.total };

    async function openCreate() {
        setError('');
        if (section === 'teachers') {
            try {
                const subjectPage = await apiRequest<PageData<SubjectRecord>>('/api/v1/academy-catalog/subjects?per_page=500');
                setSubjects(subjectPage);
            } catch (requestError) {
                setError(requestError instanceof Error ? requestError.message : 'Could not load modules for teacher assignments.');
                return;
            }
        }
        setEditor({ section, id: null, form: createForm(section) });
    }

    // Translate API relationship objects into the ID collections expected by edit controls.
    async function openEdit(id: number) {
        setError('');
        try {
            const { data } = await apiRequest<{ data: CourseRecord | BatchRecord | SubjectRecord | TeacherRecord }>(`/api/v1/academy-catalog/${section}/${id}`);
            let form: FormState;
            if (section === 'courses') {
                // Load module choices when editing a course, even if the Modules tab was never opened.
                const subjectPage = await apiRequest<PageData<SubjectRecord>>('/api/v1/academy-catalog/subjects');
                setSubjects(subjectPage);
                const course = data as CourseRecord;
                form = { ...course, subject_ids: course.subjects.map((subject) => subject.id) };
            } else if (section === 'batches') {
                const batch = data as BatchRecord;
                form = { ...batch, admission_opens_on: dateOnly(batch.admission_opens_on), admission_closes_on: dateOnly(batch.admission_closes_on), starts_on: dateOnly(batch.starts_on), expected_ends_on: dateOnly(batch.expected_ends_on), actual_ends_on: dateOnly(batch.actual_ends_on), capacity: batch.capacity ?? '' };
            } else if (section === 'subjects') {
                const subject = data as SubjectRecord;
                form = { ...subject, course_ids: subject.courses.map((course) => course.id) };
            } else {
                const subjectPage = await apiRequest<PageData<SubjectRecord>>('/api/v1/academy-catalog/subjects?per_page=500');
                setSubjects(subjectPage);
                const teacher = data as TeacherRecord;
                form = {
                    ...teacher,
                    subject_assignments: teacher.subjects.map((subject) => ({ subject_id: subject.id, teaching_role: subject.pivot.teaching_role ?? '', is_active: subject.pivot.is_active, notes: subject.pivot.notes ?? '' })),
                    branch_ids: teacher.branches.map((branch) => branch.id),
                };
            }
            setEditor({ section, id, form });
        } catch (requestError) {
            setError(requestError instanceof Error ? requestError.message : 'Could not load this record.');
        }
    }

    async function openView(id: number) {
        setError('');
        try {
            const { data } = await apiRequest<{ data: CourseRecord | BatchRecord | SubjectRecord | TeacherRecord }>(`/api/v1/academy-catalog/${section}/${id}`);
            setViewRecord({ section, record: data });
        } catch (requestError) {
            setError(requestError instanceof Error ? requestError.message : 'Could not load this record.');
        }
    }

    async function deleteRecord(id: number, label: string) {
        if (!window.confirm(`Delete ${label}? This action cannot be undone.`)) return;
        setError('');
        setNotice('');
        try {
            const result = await apiRequest<{ message?: string }>(`/api/v1/academy-catalog/${section}/${id}`, { method: 'DELETE' });
            setNotice(result.message ?? `${sectionLabels[section]} deleted.`);
            await reload(section, pages[section]);
        } catch (requestError) {
            setError(requestError instanceof Error ? requestError.message : 'Could not delete this record.');
        }
    }

    function changeField(name: string, value: unknown) {
        setEditor((current) => current ? { ...current, form: { ...current.form, [name]: value } } : current);
    }

    // Convert numeric form values before sending JSON to the API validators.
    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!editor) return;
        setSaving(true);
        setError('');
        setNotice('');
        try {
            const payload = { ...editor.form };
            for (const numberField of ['institute_id', 'course_id', 'branch_id', 'duration_months', 'completion_period_months', 'capacity']) {
                if (payload[numberField] === '') payload[numberField] = null;
                else if (payload[numberField] !== undefined && payload[numberField] !== null) payload[numberField] = Number(payload[numberField]);
            }
            const path = `/api/v1/academy-catalog/${editor.section}${editor.id ? `/${editor.id}` : ''}`;
            const result = await apiRequest<{ message?: string }>(path, {
                method: editor.id ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
            });
            setEditor(null);
            setNotice(result.message ?? `${sectionLabels[editor.section]} ${editor.id ? 'updated' : 'created'} successfully.`);
            await reload(editor.section, pages[editor.section]);
        } catch (requestError) {
            setError(requestError instanceof Error ? requestError.message : 'Could not save this record.');
        } finally {
            setSaving(false);
        }
    }

    function changePage(nextPage: number) {
        setPages((current) => ({ ...current, [section]: nextPage }));
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Academy Management | CMUD" />
            <main className="min-h-screen bg-[#F7F9FC] px-4 py-6 text-slate-800 sm:px-7 lg:px-10">
                <div className="mx-auto max-w-7xl space-y-6">
                    <header className="flex flex-col justify-between gap-4 rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-6 text-white shadow-lg sm:flex-row sm:items-end sm:p-8">
                        <div>
                            <div className="mb-3 flex items-center gap-2 text-xs font-semibold tracking-[0.16em] text-cyan-100 uppercase"><Settings2 size={15} /> Academy workspace</div>
                            <h1 className="text-3xl font-semibold tracking-tight">Academic management</h1>
                            <p className="mt-2 max-w-xl text-sm text-cyan-50/85">Manage courses, batches, course modules, and teacher assignments from one workspace.</p>
                        </div>
                        <button type="button" onClick={openCreate} className="inline-flex h-11 items-center justify-center gap-2 self-start rounded-xl bg-white px-4 text-sm font-semibold text-[#153B5B] shadow-sm transition hover:bg-cyan-50 sm:self-auto"><Plus size={17} /> Add {sectionLabels[section]}</button>
                    </header>

                    <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {sections.map(({ key, label, icon: Icon }) => <button key={key} type="button" onClick={() => { setSection(key); setQuery(''); setError(''); setNotice(''); }} className={`flex items-center gap-4 rounded-2xl border p-4 text-left transition ${section === key ? 'border-[#167B87] bg-[#EFF9FA] shadow-sm' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'}`}>
                            <span className={`grid h-11 w-11 place-items-center rounded-xl ${section === key ? 'bg-[#D5EFF0] text-[#087586]' : 'bg-slate-100 text-slate-500'}`}><Icon size={19} /></span>
                            <span className="min-w-0 flex-1"><span className="block text-sm font-semibold text-[#17375C]">{label}</span><span className="mt-0.5 block text-xs text-slate-500">{counts[key]} records</span></span>
                            {section === key && <span className="h-2 w-2 rounded-full bg-[#0D8996]" />}
                        </button>)}
                    </section>

                    {error && <div role="alert" className="flex items-start justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><span>{error}</span><button type="button" onClick={() => setError('')} aria-label="Dismiss error"><X size={16} /></button></div>}
                    {notice && <div role="status" className="flex items-start justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><span>{notice}</span><button type="button" onClick={() => setNotice('')} aria-label="Dismiss message"><X size={16} /></button></div>}

                    <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex flex-col justify-between gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:px-6">
                            <div><h2 className="text-lg font-semibold text-[#17375C]">{sections.find((item) => item.key === section)?.label}</h2><p className="mt-1 text-xs text-slate-500">Create, update, and maintain Academy records.</p></div>
                            <label className="relative block sm:w-72"><Search className="absolute top-1/2 left-3 -translate-y-1/2 text-slate-400" size={16} /><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder={`Search ${sectionLabels[section]} records...`} className="h-10 w-full rounded-xl border border-slate-200 bg-white pr-3 pl-9 text-sm outline-none focus:border-[#16828D] focus:ring-3 focus:ring-[#16828D]/10" /></label>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[760px] text-left">
                                <thead className="bg-slate-50 text-[10px] font-bold tracking-[0.12em] text-slate-400 uppercase"><tr>{tableHeaders(section).map((title) => <th key={title} className="px-5 py-3">{title}</th>)}<th className="px-5 py-3 text-right">Actions</th></tr></thead>
                                <tbody className="divide-y divide-slate-100">
                                    {loading ? <tr><td colSpan={6} className="px-5 py-14 text-center text-sm text-slate-500"><span className="inline-flex items-center gap-2"><LoaderCircle size={16} className="animate-spin" /> Loading records...</span></td></tr>
                                        : currentRows.length ? currentRows.map((record) => <ManagementRow key={`${section}-${record.id}`} section={section} record={record} onView={() => void openView(record.id)} onEdit={() => void openEdit(record.id)} onDelete={() => void deleteRecord(record.id, recordLabel(section, record))} />)
                                            : <tr><td colSpan={6} className="px-5 py-16 text-center"><span className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-400"><Layers3 size={20} /></span><p className="mt-3 text-sm font-semibold text-[#17375C]">No {sectionLabels[section]} records found</p><p className="mt-1 text-xs text-slate-500">Add a record to get started, or adjust your search.</p></td></tr>}
                                </tbody>
                            </table>
                        </div>

                        <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4 text-xs text-slate-500 sm:px-6">
                            <span>Page {activePage(section, courses, batches, subjects, teachers)} of {activeLastPage(section, courses, batches, subjects, teachers)} · {counts[section]} total records</span>
                            <div className="flex gap-2"><button type="button" disabled={loading || activePage(section, courses, batches, subjects, teachers) <= 1} onClick={() => changePage(activePage(section, courses, batches, subjects, teachers) - 1)} className="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 text-slate-600 disabled:text-slate-300" aria-label="Previous page"><ChevronLeft size={16} /></button><button type="button" disabled={loading || activePage(section, courses, batches, subjects, teachers) >= activeLastPage(section, courses, batches, subjects, teachers)} onClick={() => changePage(activePage(section, courses, batches, subjects, teachers) + 1)} className="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 text-slate-600 disabled:text-slate-300" aria-label="Next page"><ChevronRight size={16} /></button></div>
                        </footer>
                    </section>
                </div>
            </main>

            {editor && <EditorModal section={editor.section} id={editor.id} form={editor.form} institutes={institutes} branches={branches} courses={courses.data} subjects={subjects.data} saving={saving} error={error} onChange={changeField} onClose={() => setEditor(null)} onSubmit={submit} />}
            {viewRecord && <RecordDetailsModal section={viewRecord.section} record={viewRecord.record} onClose={() => setViewRecord(null)} />}
        </AppLayout>
    );
}

function tableHeaders(section: Section): string[] {
    switch (section) {
        case 'courses': return ['Course', 'Institute', 'Duration', 'Modules', 'Status'];
        case 'batches': return ['Batch', 'Course', 'Branch', 'Starts', 'Status'];
        case 'subjects': return ['Module / subject', 'Code', 'Courses', 'Status'];
        case 'teachers': return ['Teacher', 'Institute', 'Phone', 'Modules / subjects', 'Branches'];
    }
}

function recordLabel(section: Section, record: CourseRecord | BatchRecord | SubjectRecord | TeacherRecord): string {
    if (section === 'courses') return (record as CourseRecord).name;
    if (section === 'batches') return (record as BatchRecord).name;
    if (section === 'subjects') return (record as SubjectRecord).name;
    return (record as TeacherRecord).full_name;
}

/** API date casts may include a time component; date inputs require YYYY-MM-DD. */
function dateOnly(value: string | null | undefined): string {
    return value ? value.slice(0, 10) : '';
}

function ManagementRow({ section, record, onView, onEdit, onDelete }: { section: Section; record: CourseRecord | BatchRecord | SubjectRecord | TeacherRecord; onView: () => void; onEdit: () => void; onDelete: () => void }) {
    const cells: ReactNode[] = [];
    if (section === 'courses') {
        const course = record as CourseRecord;
        cells.push(<CellTitle key="title" title={course.name} subtitle={course.code} />, <span key="institute" className="text-sm text-slate-600">{course.institute?.name ?? '—'}</span>, <span key="duration" className="text-sm text-slate-600">{course.duration_months} mo · max {course.completion_period_months} mo</span>, <span key="modules" className="inline-flex items-center gap-1.5 text-sm text-slate-600"><BookOpen size={14} /> {course.subjects?.length ?? 0}</span>, <StatusBadge key="status" active={course.is_active} />);
    } else if (section === 'batches') {
        const batch = record as BatchRecord;
        cells.push(<CellTitle key="title" title={batch.name} subtitle={batch.code} />, <span key="course" className="text-sm text-slate-600">{batch.course?.name ?? '—'}</span>, <span key="branch" className="text-sm text-slate-600">{batch.branch?.name ?? '—'}</span>, <span key="starts" className="text-sm text-slate-600">{batch.starts_on}</span>, <StatusBadge key="status" label={batch.status} active={batch.status === 'active' || batch.status === 'open'} />);
    } else if (section === 'subjects') {
        const subject = record as SubjectRecord;
        cells.push(<CellTitle key="title" title={subject.name} subtitle={subject.description ?? 'Course module'} />, <span key="code" className="font-mono text-xs text-slate-600">{subject.code}</span>, <span key="courses" className="text-sm text-slate-600">{subject.courses?.length ?? 0} courses</span>, <StatusBadge key="status" active={subject.is_active} />);
    } else {
        const teacher = record as TeacherRecord;
        cells.push(<CellTitle key="title" title={teacher.full_name} subtitle={teacher.teacher_code} />, <span key="institute" className="text-sm text-slate-600">{teacher.institute?.name ?? '—'}</span>, <span key="phone" className="text-sm text-slate-600">{teacher.phone}</span>, <span key="subjects" className="text-sm text-slate-600">{teacher.subjects?.length ?? 0} modules / subjects</span>, <span key="branches" className="text-sm text-slate-600">{teacher.branches?.length ?? 0} branches</span>);
    }

    return <tr className="transition hover:bg-slate-50/70">{cells.map((cell, index) => <td key={index} className="px-5 py-4">{cell}</td>)}<td className="px-5 py-4"><div className="flex justify-end gap-1"><button type="button" onClick={onView} aria-label="View record" className="inline-flex h-8 items-center gap-1 rounded-lg px-2 text-xs font-semibold text-[#087586] hover:bg-cyan-50"><Eye size={14} /> View</button><button type="button" onClick={onEdit} aria-label="Edit record" className="grid h-8 w-8 place-items-center rounded-lg text-slate-500 hover:bg-cyan-50 hover:text-[#087586]"><Pencil size={15} /></button><button type="button" onClick={onDelete} aria-label="Delete record" className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600"><Trash2 size={15} /></button></div></td></tr>;
}

/** Read-only detail dialog showing the selected record and its linked records. */
function RecordDetailsModal({ section, record, onClose }: { section: Section; record: CourseRecord | BatchRecord | SubjectRecord | TeacherRecord; onClose: () => void }) {
    const details: Array<[string, ReactNode]> = [];
    if (section === 'courses') {
        const course = record as CourseRecord;
        details.push(['Course code', course.code], ['Name', course.name], ['Institute', course.institute?.name ?? '—'], ['Mode', course.course_mode], ['Duration', `${course.duration_months} months`], ['Completion period', `${course.completion_period_months} months`], ['Status', course.is_active ? 'Active' : 'Inactive'], ['Description', course.description || '—']);
    } else if (section === 'batches') {
        const batch = record as BatchRecord;
        details.push(['Batch code', batch.code], ['Name', batch.name], ['Course', batch.course ? `${batch.course.name} (${batch.course.code})` : '—'], ['Branch', batch.branch?.name ?? '—'], ['Status', batch.status], ['Admission opens', dateOnly(batch.admission_opens_on) || '—'], ['Admission closes', dateOnly(batch.admission_closes_on) || '—'], ['Starts', dateOnly(batch.starts_on)], ['Expected end', dateOnly(batch.expected_ends_on)], ['Actual end', dateOnly(batch.actual_ends_on) || '—'], ['Capacity', batch.capacity?.toString() ?? '—']);
    } else if (section === 'subjects') {
        const subject = record as SubjectRecord;
        details.push(['Module code', subject.code], ['Name', subject.name], ['Status', subject.is_active ? 'Active' : 'Inactive'], ['Description', subject.description || '—'], ['Courses', subject.courses?.map((course) => `${course.name} (${course.code})`).join(', ') || 'None']);
    } else {
        const teacher = record as TeacherRecord;
        details.push(['Teacher code', teacher.teacher_code], ['Name', teacher.full_name], ['Institute', teacher.institute?.name ?? '—'], ['Phone', teacher.phone], ['Email', teacher.email || '—'], ['Qualification', teacher.qualification || '—'], ['Specialization', teacher.specialization || '—'], ['Designation', teacher.designation || '—'], ['Organization', teacher.organization || '—'], ['BMDC number', teacher.bmdc_number || '—'], ['Status', teacher.is_active ? 'Active' : 'Inactive'], ['Branches', teacher.branches?.map((branch) => branch.name).join(', ') || 'None'], ['Notes', teacher.notes || '—']);
    }

    return <div className="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto bg-[#10233B]/55 p-4 backdrop-blur-[1px]" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
        <section role="dialog" aria-modal="true" aria-labelledby="academy-record-title" className="my-auto max-h-[calc(100vh-2rem)] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <header className="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white px-6 py-5"><div><p className="text-[10px] font-bold tracking-[0.15em] text-[#087586] uppercase">Academy record</p><h2 id="academy-record-title" className="mt-1 text-lg font-semibold text-[#17375C]">{recordLabel(section, record)}</h2></div><button type="button" onClick={onClose} aria-label="Close record details" className="rounded-lg p-2 text-slate-400 hover:bg-slate-100"><X size={18} /></button></header>
            <dl className="grid gap-4 p-6 sm:grid-cols-2">{details.map(([label, value]) => <div key={label} className="min-w-0 rounded-xl border border-slate-100 bg-slate-50/70 p-3"><dt className="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{label}</dt><dd className="mt-1.5 break-words text-sm text-slate-700">{value}</dd></div>)}</dl>
            {section === 'courses' && <CourseRelationships course={record as CourseRecord} />}
            {section === 'teachers' && <TeacherSubjectAssignments teacher={record as TeacherRecord} />}
            <footer className="flex justify-end border-t border-slate-100 px-6 py-4"><button type="button" onClick={onClose} className="rounded-lg bg-[#17375C] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#254878]">Close</button></footer>
        </section>
    </div>;
}

/** Presents a course's linked modules and batches as separate, scannable lists. */
function CourseRelationships({ course }: { course: CourseRecord }) {
    const modules = course.subjects ?? [];
    const batches = course.batches ?? [];

    return <div className="space-y-5 px-6 pb-6">
        <section aria-labelledby="course-modules-title" className="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <header className="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-4 py-3">
                <div className="flex items-center gap-2"><BookOpen size={16} className="text-[#087586]" /><h3 id="course-modules-title" className="text-sm font-semibold text-[#17375C]">Modules / subjects</h3></div>
                <span className="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-[#087586]">{modules.length}</span>
            </header>
            {modules.length ? <ul className="divide-y divide-slate-100">{modules.map((module) => <li key={module.id} className="flex items-center justify-between gap-3 px-4 py-3"><span className="min-w-0 truncate text-sm font-medium text-slate-700">{module.name}</span><span className="shrink-0 rounded-md bg-slate-100 px-2 py-1 font-mono text-[11px] text-slate-500">{module.code}</span></li>)}</ul> : <p className="px-4 py-5 text-sm text-slate-500">No modules or subjects are linked to this course yet.</p>}
        </section>
        <section aria-labelledby="course-batches-title" className="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <header className="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-4 py-3">
                <div className="flex items-center gap-2"><CalendarDays size={16} className="text-[#087586]" /><h3 id="course-batches-title" className="text-sm font-semibold text-[#17375C]">Batches</h3></div>
                <span className="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-[#087586]">{batches.length}</span>
            </header>
            {batches.length ? <ul className="divide-y divide-slate-100">{batches.map((batch) => <li key={batch.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div className="min-w-0"><p className="truncate text-sm font-medium text-slate-700">{batch.name}</p><p className="mt-1 text-xs text-slate-500">{batch.code}{batch.branch?.name ? ` · ${batch.branch.name}` : ''}{batch.starts_on ? ` · Starts ${dateOnly(batch.starts_on)}` : ''}</p></div>
                <div className="flex items-center gap-2"><span className="text-xs text-slate-500">{batch.capacity != null ? `${batch.capacity} seats` : ''}</span><StatusBadge label={batch.status} active={batch.status === 'active' || batch.status === 'open'} /></div>
            </li>)}</ul> : <p className="px-4 py-5 text-sm text-slate-500">No batches have been created for this course yet.</p>}
        </section>
    </div>;
}

/** Shows each teacher's assigned courses together with the modules attached to those courses. */
function TeacherCourseAssignments({ teacher }: { teacher: TeacherRecord }) {
    const assignedCourses = teacher.courses ?? [];

    return <section aria-labelledby="teacher-course-assignments-title" className="px-6 pb-6">
        <div className="mb-3 flex items-center justify-between gap-3"><div><h3 id="teacher-course-assignments-title" className="text-sm font-semibold text-[#17375C]">Course and module assignments</h3><p className="mt-1 text-xs text-slate-500">Modules are shown beneath each assigned course.</p></div><span className="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-[#087586]">{assignedCourses.length} courses</span></div>
        {assignedCourses.length ? <ul className="space-y-3">{assignedCourses.map((course) => <li key={course.id} className="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div className="flex flex-wrap items-center justify-between gap-2 bg-slate-50/80 px-4 py-3"><div><p className="text-sm font-semibold text-slate-700">{course.name}</p><p className="mt-0.5 font-mono text-[11px] text-slate-500">{course.code}</p></div><div className="flex flex-wrap items-center gap-2">{course.pivot.teaching_role && <span className="rounded-full bg-white px-2.5 py-1 text-[11px] text-slate-600">{course.pivot.teaching_role}</span>}<StatusBadge label={course.pivot.is_active ? 'Assignment active' : 'Assignment inactive'} active={course.pivot.is_active} /></div></div>
            <div className="px-4 py-3"><p className="mb-2 text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Modules / subjects ({course.subjects?.length ?? 0})</p>{course.subjects?.length ? <ul className="flex flex-wrap gap-2">{course.subjects.map((module) => <li key={module.id} className="inline-flex items-center gap-1.5 rounded-lg border border-cyan-100 bg-cyan-50/70 px-2.5 py-1.5 text-xs text-[#17636D]"><span>{module.name}</span><span className="font-mono text-[10px] text-[#087586]/70">{module.code}</span></li>)}</ul> : <p className="text-xs text-slate-500">No modules or subjects are linked to this course.</p>}</div>
        </li>)}</ul> : <p className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">No course or module assignments are recorded for this teacher.</p>}
    </section>;
}

function EditorModal({ section, id, form, institutes, branches, courses, subjects, saving, error, onChange, onClose, onSubmit }: { section: Section; id: number | null; form: FormState; institutes: InstituteOption[]; branches: BranchOption[]; courses: CourseRecord[]; subjects: SubjectOption[]; saving: boolean; error: string; onChange: (name: string, value: unknown) => void; onClose: () => void; onSubmit: (event: FormEvent<HTMLFormElement>) => void }) {
    const title = `${id ? 'Edit' : 'Add'} ${sectionLabels[section]}`;
    const input = (name: string, label: string, type = 'text', required = false, extra: Record<string, string> = {}) => <FormInput key={name} name={name} label={label} type={type} required={required} value={form[name] == null ? '' : String(form[name])} extra={extra} onChange={(value) => onChange(name, type === 'number' ? (value === '' ? '' : Number(value)) : value)} />;
    const select = (name: string, label: string, options: Array<{ id: number | string; name: string }>, required = false, afterChange?: (value: string) => void, disabled = false) => <label key={name} className="block text-xs font-medium text-slate-600">{label}{required && <span className="ml-1 text-rose-500">*</span>}<select required={required} disabled={disabled} value={String(form[name] ?? '')} onChange={(event) => { const value = event.target.value; onChange(name, value ? Number(value) : ''); afterChange?.(value); }} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-[#16828D] disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"><option value="">Select {label.toLowerCase()}</option>{options.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}</select></label>;

    const selectedBatchCourse = section === 'batches' ? courses.find((course) => course.id === Number(form.course_id)) : undefined;
    const batchBranches = selectedBatchCourse
        ? branches.filter((branch) => branch.institute_id === selectedBatchCourse.institute_id)
        : [];

    function toggleId(field: string, idValue: number, checked: boolean) {
        const current = Array.isArray(form[field]) ? form[field] as number[] : [];
        onChange(field, checked ? [...current, idValue] : current.filter((value) => value !== idValue));
    }

    function toggleTeacherCourse(courseId: number, checked: boolean) {
        const current = Array.isArray(form.course_assignments) ? form.course_assignments as CourseAssignment[] : [];
        const next = checked
            ? [...current, { course_id: courseId, teaching_role: '', is_active: true, notes: '' }]
            : current.filter((assignment) => assignment.course_id !== courseId);
        onChange('course_assignments', next);
    }

    return <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-[#10233B]/55 p-4 backdrop-blur-[1px]" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
        <section role="dialog" aria-modal="true" aria-labelledby="academy-editor-title" className="my-auto max-h-[calc(100vh-2rem)] w-full max-w-3xl overflow-y-auto rounded-2xl bg-[#F8FAFC] shadow-2xl">
            <header className="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white px-6 py-5"><div><p className="text-[10px] font-bold tracking-[0.15em] text-[#087586] uppercase">Academy catalog</p><h2 id="academy-editor-title" className="mt-1 text-lg font-semibold text-[#17375C]">{title}</h2></div><button type="button" onClick={onClose} aria-label="Close form" className="rounded-lg p-2 text-slate-400 hover:bg-slate-100"><X size={18} /></button></header>
            <form onSubmit={onSubmit} className="space-y-5 p-6">
                {error && <p role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</p>}
                {section === 'courses' && <div className="grid gap-4 sm:grid-cols-2">
                    {select('institute_id', 'Institute', institutes, true)}{input('code', 'Course code', 'text', true)}{input('name', 'Course name', 'text', true)}{input('duration_months', 'Duration in months', 'number', true, { min: '1' })}{input('completion_period_months', 'Maximum completion in months', 'number', true, { min: '1' })}
                    <SelectTextField label="Course mode" value={String(form.course_mode ?? 'offline')} options={['online', 'offline', 'dual']} onChange={(value) => onChange('course_mode', value)} />
                    <div className="sm:col-span-2">{input('description', 'Description')}</div>
                    <div className="sm:col-span-2"><SearchableMultiSelect title="Course modules / subjects" options={subjects} selected={Array.isArray(form.subject_ids) ? form.subject_ids as number[] : []} onToggle={(subjectId, checked) => toggleId('subject_ids', subjectId, checked)} emptyText="Create subjects first to attach modules." /></div>
                    <div className="sm:col-span-2"><BooleanField label="Course is active" checked={Boolean(form.is_active)} onChange={(checked) => onChange('is_active', checked)} /></div>
                </div>}
                {section === 'batches' && <div className="grid gap-4 sm:grid-cols-2">
                    {select('course_id', 'Course', courses, true, () => onChange('branch_id', ''))}{select('branch_id', 'Branch', batchBranches, true, undefined, !selectedBatchCourse)}{input('code', 'Batch code', 'text', true)}{input('name', 'Batch name', 'text', true)}{input('admission_opens_on', 'Admission opens', 'date')}{input('admission_closes_on', 'Admission closes', 'date')}{input('starts_on', 'Starts on', 'date', true)}{input('expected_ends_on', 'Expected end date', 'date', true)}{input('actual_ends_on', 'Actual end date', 'date')}{input('capacity', 'Capacity', 'number', false, { min: '1' })}
                    <SelectTextField label="Batch status" value={String(form.status ?? 'active')} options={['active', 'open', 'closed', 'completed', 'cancelled']} onChange={(value) => onChange('status', value)} />
                    <div className="sm:col-span-2">{input('notes', 'Notes')}</div>
                </div>}
                {section === 'subjects' && <div className="grid gap-4 sm:grid-cols-2">
                    {input('code', 'Module code', 'text', true)}{input('name', 'Module / subject name', 'text', true)}<div className="sm:col-span-2">{input('description', 'Description')}</div>
                    <div className="sm:col-span-2"><CheckList title="Linked courses" options={courses} selected={Array.isArray(form.course_ids) ? form.course_ids as number[] : []} onToggle={(courseId, checked) => toggleId('course_ids', courseId, checked)} emptyText="Create courses first to attach this module." /></div>
                    <div className="sm:col-span-2"><BooleanField label="Module is active" checked={Boolean(form.is_active)} onChange={(checked) => onChange('is_active', checked)} /></div>
                </div>}
                {section === 'teachers' && <div className="grid gap-4 sm:grid-cols-2">
                    {select('institute_id', 'Institute', institutes, true)}{input('teacher_code', 'Teacher code', 'text', true)}{input('full_name', 'Full name', 'text', true)}{input('phone', 'Phone', 'tel', true)}{input('email', 'Email', 'email')}{input('qualification', 'Qualification')}{input('specialization', 'Specialization')}{input('designation', 'Designation')}{input('organization', 'Organization')}{input('bmdc_number', 'BMDC number')}<div className="sm:col-span-2">{input('address', 'Address')}</div><div className="sm:col-span-2">{input('notes', 'Notes')}</div>
                    <div className="sm:col-span-2"><CheckList title="Branch assignments" options={branches} selected={Array.isArray(form.branch_ids) ? form.branch_ids as number[] : []} onToggle={(branchId, checked) => toggleId('branch_ids', branchId, checked)} emptyText="No branches are available." /></div>
                    <div className="sm:col-span-2"><div className="rounded-xl border border-slate-200 bg-white p-4"><h3 className="text-xs font-semibold text-slate-700">Course assignments</h3><p className="mt-1 text-[11px] text-slate-500">Select courses and set the teacher’s role for each one.</p><div className="mt-3 space-y-2">{courses.map((course) => {
                        const assignments = Array.isArray(form.course_assignments) ? form.course_assignments as CourseAssignment[] : [];
                        const assignment = assignments.find((item) => item.course_id === course.id);
                        return <div key={course.id} className="flex flex-wrap items-center gap-3 border-t border-slate-100 py-2"><label className="flex min-w-48 flex-1 items-center gap-2 text-xs text-slate-700"><input type="checkbox" checked={Boolean(assignment)} onChange={(event) => toggleTeacherCourse(course.id, event.target.checked)} className="rounded border-slate-300 text-[#087586] focus:ring-[#087586]" />{course.name} <span className="font-mono text-slate-400">{course.code}</span></label>{assignment && <input value={assignment.teaching_role} onChange={(event) => onChange('course_assignments', assignments.map((item) => item.course_id === course.id ? { ...item, teaching_role: event.target.value } : item))} placeholder="Teaching role (optional)" className="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs sm:w-52" />}</div>;
                    })}</div></div></div>
                    <div className="sm:col-span-2"><BooleanField label="Teacher is active" checked={Boolean(form.is_active)} onChange={(checked) => onChange('is_active', checked)} /></div>
                </div>}
                <footer className="sticky bottom-0 flex justify-end gap-2 border-t border-slate-200 bg-[#F8FAFC] pt-4"><button type="button" onClick={onClose} className="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button type="submit" disabled={saving} className="inline-flex items-center gap-2 rounded-lg bg-[#17375C] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#254878] disabled:opacity-60">{saving && <LoaderCircle size={15} className="animate-spin" />}{saving ? 'Saving...' : id ? 'Save changes' : 'Create record'}</button></footer>
            </form>
        </section>
    </div>;
}

function FormInput({ name, label, type, required, value, extra, onChange }: { name: string; label: string; type: string; required: boolean; value: string; extra: Record<string, string>; onChange: (value: string) => void }) {
    return <label className="block text-xs font-medium text-slate-600">{label}{required && <span className="ml-1 text-rose-500">*</span>}<input id={name} name={name} type={type} required={required} value={value} onChange={(event) => onChange(event.target.value)} {...extra} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-[#16828D] focus:ring-3 focus:ring-[#16828D]/10" /></label>;
}

function SelectTextField({ label, value, options, onChange }: { label: string; value: string; options: string[]; onChange: (value: string) => void }) {
    return <label className="block text-xs font-medium text-slate-600">{label}<select value={value} onChange={(event) => onChange(event.target.value)} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-[#16828D]">{options.map((option) => <option key={option} value={option}>{option.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())}</option>)}</select></label>;
}

function CheckList({ title, options, selected, onToggle, emptyText }: { title: string; options: Array<{ id: number; name: string; code?: string }>; selected: number[]; onToggle: (id: number, checked: boolean) => void; emptyText: string }) {
    return <div className="rounded-xl border border-slate-200 bg-white p-4"><h3 className="text-xs font-semibold text-slate-700">{title}</h3>{options.length ? <div className="mt-3 grid max-h-48 gap-2 overflow-y-auto sm:grid-cols-2">{options.map((option) => <label key={option.id} className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-slate-600 hover:bg-slate-50"><input type="checkbox" checked={selected.includes(option.id)} onChange={(event) => onToggle(option.id, event.target.checked)} className="rounded border-slate-300 text-[#087586] focus:ring-[#087586]" /><span className="min-w-0 truncate">{option.name}</span>{option.code && <span className="ml-auto shrink-0 font-mono text-[10px] text-slate-400">{option.code}</span>}</label>)}</div> : <p className="mt-2 text-xs text-slate-400">{emptyText}</p>}</div>;
}

/** Searchable multi-select keeps large module catalogs manageable during course edits. */
function SearchableMultiSelect({ title, options, selected, onToggle, emptyText }: { title: string; options: Array<{ id: number; name: string; code?: string }>; selected: number[]; onToggle: (id: number, checked: boolean) => void; emptyText: string }) {
    const [search, setSearch] = useState('');
    const [open, setOpen] = useState(false);
    const matchingOptions = options.filter((option) => `${option.name} ${option.code ?? ''}`.toLowerCase().includes(search.trim().toLowerCase()));
    const selectedOptions = options.filter((option) => selected.includes(option.id));

    return <div className="rounded-xl border border-slate-200 bg-white p-4">
        <div className="flex items-center justify-between gap-3"><div><h3 className="text-xs font-semibold text-slate-700">{title}</h3><p className="mt-1 text-[11px] text-slate-500">Search and select one or more modules.</p></div><span className="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">{selected.length} selected</span></div>
        <div className="relative mt-3">
            <Search size={15} className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-slate-400" />
            <input type="search" value={search} onFocus={() => setOpen(true)} onChange={(event) => { setSearch(event.target.value); setOpen(true); }} onKeyDown={(event) => { if (event.key === 'Escape') setOpen(false); if (event.key === 'Enter' && matchingOptions[0]) { event.preventDefault(); onToggle(matchingOptions[0].id, !selected.includes(matchingOptions[0].id)); } }} placeholder="Search modules by name or code" aria-label={`Search ${title.toLowerCase()}`} aria-expanded={open} className="h-10 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-9 text-sm outline-none placeholder:text-slate-400 focus:border-[#16828D] focus:ring-3 focus:ring-[#16828D]/10" />
            {open && <div className="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg" role="listbox" aria-multiselectable="true" aria-label={title}>
                {matchingOptions.length ? matchingOptions.map((option) => {
                    const isSelected = selected.includes(option.id);
                    return <button key={option.id} type="button" role="option" aria-selected={isSelected} onMouseDown={(event) => event.preventDefault()} onClick={() => onToggle(option.id, !isSelected)} className="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-cyan-50 focus:bg-cyan-50 focus:outline-none">
                        <span className={`grid h-4 w-4 shrink-0 place-items-center rounded border ${isSelected ? 'border-[#087586] bg-[#087586] text-white' : 'border-slate-300 text-transparent'}`}><Check size={12} /></span>
                        <span className="min-w-0 flex-1 truncate text-sm text-slate-700">{option.name}</span><span className="shrink-0 font-mono text-[10px] text-slate-400">{option.code}</span>
                    </button>;
                }) : <p className="px-3 py-3 text-sm text-slate-500">{options.length ? 'No matching modules found.' : emptyText}</p>}
            </div>}
        </div>
        {selectedOptions.length > 0 && <ul className="mt-3 flex flex-wrap gap-2" aria-label="Selected modules">{selectedOptions.map((option) => <li key={option.id} className="inline-flex max-w-full items-center gap-1.5 rounded-full border border-cyan-100 bg-cyan-50 py-1 pl-3 pr-1 text-xs text-[#17636D]"><span className="truncate">{option.name}</span><button type="button" onClick={() => onToggle(option.id, false)} aria-label={`Remove ${option.name}`} className="grid h-6 w-6 shrink-0 place-items-center rounded-full hover:bg-cyan-100"><X size={13} /></button></li>)}</ul>}
        {open && <button type="button" onClick={() => setOpen(false)} className="mt-2 text-[11px] font-medium text-[#087586] hover:underline">Close module list</button>}
    </div>;
}

function BooleanField({ label, checked, onChange }: { label: string; checked: boolean; onChange: (checked: boolean) => void }) {
    return <label className="inline-flex items-center gap-2 text-xs font-medium text-slate-600"><input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} className="rounded border-slate-300 text-[#087586] focus:ring-[#087586]" />{label}</label>;
}

function CellTitle({ title, subtitle }: { title: string; subtitle: string }) {
    return <div className="min-w-40"><p className="text-sm font-semibold text-[#17375C]">{title}</p><p className="mt-1 text-xs text-slate-400">{subtitle}</p></div>;
}

function StatusBadge({ active, label = active ? 'Active' : 'Inactive' }: { active: boolean; label?: string }) {
    return <span className={`inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold ${active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>{label}</span>;
}

function activePage(section: Section, courses: PageData<CourseRecord>, batches: PageData<BatchRecord>, subjects: PageData<SubjectRecord>, teachers: PageData<TeacherRecord>): number {
    return section === 'courses' ? courses.current_page : section === 'batches' ? batches.current_page : section === 'subjects' ? subjects.current_page : teachers.current_page;
}

function activeLastPage(section: Section, courses: PageData<CourseRecord>, batches: PageData<BatchRecord>, subjects: PageData<SubjectRecord>, teachers: PageData<TeacherRecord>): number {
    return section === 'courses' ? courses.last_page : section === 'batches' ? batches.last_page : section === 'subjects' ? subjects.last_page : teachers.last_page;
}
