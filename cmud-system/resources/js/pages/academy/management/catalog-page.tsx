import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { BookOpen, BriefcaseBusiness, CalendarDays, Check, ChevronLeft, ChevronRight, Eye, GraduationCap, Layers3, LoaderCircle, Pencil, Plus, Search, Settings2, Trash2, Users, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';

type InstituteOption = { id: number; name: string };
type BranchOption = { id: number; institute_id: number; name: string };
type CourseOption = { id: number; code: string; name: string; institute_id?: number };
type PickerOption = { id: number; name: string; code?: string; course?: Pick<CourseOption, 'name' | 'code'> };
type SubjectOption = { id: number; code: string; name: string; courses?: CourseOption[]; course_stages?: Array<{ id: number; code: string; name: string; course?: CourseOption }> };
type CourseStageRecord = { id: number; code: string; name: string; sequence_number: number; duration_months: number; certificate_enabled: boolean; requires_exam: boolean; subjects: SubjectOption[] };
type CourseRecord = CourseOption & { institute_id: number; description: string | null; duration_months: number; completion_period_months: number; course_mode: string; is_active: boolean; institute?: InstituteOption; subjects: SubjectOption[]; stages?: CourseStageRecord[]; batches: BatchRecord[] };
type BatchRecord = { id: number; course_id: number; branch_id: number; code: string; name: string; admission_opens_on?: string | null; admission_closes_on?: string | null; starts_on: string; expected_ends_on: string; actual_ends_on?: string | null; status: string; capacity: number | null; enrolled_students_count?: number; course?: CourseOption; branch?: BranchOption };
type SubjectRecord = SubjectOption & { description: string | null; is_active: boolean; teachers?: Array<{ id: number; full_name: string }> };
type SubjectAssignment = { subject_id: number; teaching_role: string; is_active: boolean; notes: string };
type TeacherRecord = { id: number; institute_id: number; stuff_id: number | null; teacher_code: string; full_name: string; phone: string; email: string | null; qualification: string | null; specialization: string | null; designation: string | null; organization: string | null; bmdc_number: string | null; address: string | null; photo_path: string | null; is_active: boolean; notes: string | null; institute?: InstituteOption; subjects: Array<SubjectOption & { pivot: { teaching_role: string | null; is_active: boolean; notes: string | null } }>; branches: BranchOption[] };
type PageData<T> = { data: T[]; current_page: number; last_page: number; from: number | null; to: number | null; total: number };
type Section = 'courses' | 'batches' | 'subjects' | 'teachers';
type FormState = Record<string, unknown>;

export type ManagementPageProps = { institutes: InstituteOption[]; branches: BranchOption[] };

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

/** Keep numbered pagination compact on large Academy catalog lists. */
function paginationItems(currentPage: number, lastPage: number): Array<number | '…'> {
    const visiblePageCount = 5;

    if (lastPage <= visiblePageCount + 1) {
        return Array.from({ length: lastPage }, (_, index) => index + 1);
    }

    const start = Math.min(Math.max(1, currentPage - Math.floor(visiblePageCount / 2)), lastPage - visiblePageCount + 1);
    const end = start + visiblePageCount - 1;
    const items: Array<number | '…'> = [];

    if (start > 1) {
        items.push(1);
        if (start > 2) items.push('…');
    }
    for (let pageNumber = start; pageNumber <= end; pageNumber += 1) items.push(pageNumber);
    if (end < lastPage) {
        if (end < lastPage - 1) items.push('…');
        items.push(lastPage);
    }

    return items;
}

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
            return { institute_id: '', code: '', name: '', description: '', duration_months: 1, completion_period_months: 1, course_duration: 1, course_duration_unit: 'months', course_validity: 1, course_validity_unit: 'months', structure_type: 'semesters', stages: [{ name: 'Semester 1', duration: 1, duration_unit: 'months', subject_ids: [] }], subject_ids: [], course_mode: 'offline', is_active: true };
        case 'batches':
            return { course_id: '', branch_id: '', code: '', name: '', admission_opens_on: '', admission_closes_on: '', starts_on: '', expected_ends_on: '', capacity: '', status: 'active', notes: '' };
        case 'subjects':
            return { code: '', name: '', description: '', is_active: true };
        case 'teachers':
            return { institute_id: '', teacher_code: '', full_name: '', phone: '', email: '', qualification: '', specialization: '', designation: '', organization: '', bmdc_number: '', address: '', is_active: true, notes: '', subject_assignments: [], branch_ids: [] };
    }
}

export default function CatalogPage({ section, institutes, branches }: ManagementPageProps & { section: Section }) {
    const title = sections.find((item) => item.key === section)!.label;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Academic management', href: '/academy/manage/courses' },
        { title, href: `/academy/manage/${section}` },
    ];
    const [records, setRecords] = useState<PageData<CourseRecord | BatchRecord | SubjectRecord | TeacherRecord>>({ data: [], current_page: 1, last_page: 1, from: null, to: null, total: 0 });
    const [courseOptions, setCourseOptions] = useState<CourseRecord[]>([]);
    const [subjectOptions, setSubjectOptions] = useState<SubjectRecord[]>([]);
    const [page, setPage] = useState(1);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [query, setQuery] = useState('');
    const [searchTerm, setSearchTerm] = useState('');
    const [editor, setEditor] = useState<{ section: Section; id: number | null; form: FormState } | null>(null);
    const [viewRecord, setViewRecord] = useState<{ section: Section; record: CourseRecord | BatchRecord | SubjectRecord | TeacherRecord } | null>(null);
    const [pendingCourseDelete, setPendingCourseDelete] = useState<{ id: number; label: string } | null>(null);
    const [pendingSubjectDelete, setPendingSubjectDelete] = useState<{ id: number; label: string } | null>(null);
    const [pendingBatchDelete, setPendingBatchDelete] = useState<{ id: number; courseId: number; label: string } | null>(null);

    // Fetch only the resource for the current page.
    const reload = useCallback(async (target: Section, page: number) => {
        const search = target === 'subjects' && searchTerm.trim() ? `&search=${encodeURIComponent(searchTerm.trim())}` : '';
        const result = await apiRequest<PageData<CourseRecord | BatchRecord | SubjectRecord | TeacherRecord>>(`/api/v1/academy-catalog/${target}?page=${page}&per_page=15${search}`);
        setRecords(result);
    }, [searchTerm]);

    // Debounce module search so each keystroke does not send a separate request.
    useEffect(() => {
        const timeout = window.setTimeout(() => {
            setSearchTerm(query);
            setPage(1);
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [query]);

    useEffect(() => {
        const controller = new AbortController();
        async function loadPage() {
            setLoading(true);
            setError('');
            try {
                const search = section === 'subjects' && searchTerm.trim() ? `&search=${encodeURIComponent(searchTerm.trim())}` : '';
                const result = await apiRequest<PageData<CourseRecord | BatchRecord | SubjectRecord | TeacherRecord>>(`/api/v1/academy-catalog/${section}?page=${page}&per_page=15${search}`, { signal: controller.signal });
                setRecords(result);
            } catch (requestError) {
                if (requestError instanceof DOMException && requestError.name === 'AbortError') return;
                setError(requestError instanceof Error ? requestError.message : 'Could not load Academy records.');
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }
        void loadPage();
        return () => controller.abort();
    }, [section, page, searchTerm]);

    const currentRows = useMemo(() => {
        if (section === 'subjects' || !query.trim()) return records.data;

        const normalizedQuery = query.trim().toLowerCase();
        return records.data.filter((record) => JSON.stringify(record).toLowerCase().includes(normalizedQuery));
    }, [records.data, query, section]);


    async function loadCourseOptions() {
        const firstPage = await apiRequest<PageData<CourseRecord>>('/api/v1/academy-catalog/courses?page=1&per_page=100');
        const remainingPages = await Promise.all(
            Array.from({ length: Math.max(0, firstPage.last_page - 1) }, (_, index) =>
                apiRequest<PageData<CourseRecord>>(`/api/v1/academy-catalog/courses?page=${index + 2}&per_page=100`),
            ),
        );
        setCourseOptions([...firstPage.data, ...remainingPages.flatMap((page) => page.data)]);
    }

    async function loadSubjectOptions() {
        const page = await apiRequest<PageData<SubjectRecord>>('/api/v1/academy-catalog/subjects?per_page=500');
        setSubjectOptions(page.data);
    }

    async function openCreate() {
        setError('');
        if (section === 'teachers') {
            try {
                await loadSubjectOptions();
            } catch (requestError) {
                setError(requestError instanceof Error ? requestError.message : 'Could not load modules for teacher assignments.');
                return;
            }
        } else if (section === 'courses') {
            try {
                await Promise.all([loadCourseOptions(), loadSubjectOptions()]);
            } catch (requestError) {
                setError(requestError instanceof Error ? requestError.message : 'Could not load course setup options.');
                return;
            }
        } else if (section === 'batches') {
            try {
                await loadCourseOptions();
            } catch (requestError) {
                setError(requestError instanceof Error ? requestError.message : 'Could not load courses for this record.');
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
                const course = data as CourseRecord;
                await loadSubjectOptions();
                const stages = course.stages ?? [];
                form = {
                    ...course,
                    course_duration: course.duration_months,
                    course_duration_unit: 'months',
                    course_validity: course.completion_period_months,
                    course_validity_unit: 'months',
                    structure_type: stages.length ? 'semesters' : 'single',
                    stages: stages.map((stage) => ({
                        id: stage.id,
                        name: stage.name,
                        duration: stage.duration_months,
                        duration_unit: 'months',
                        certificate_enabled: stage.certificate_enabled,
                        subject_ids: stage.subjects.map((subject) => subject.id),
                    })),
                    subject_ids: course.subjects.map((subject) => subject.id),
                };
            } else if (section === 'batches') {
                const batch = data as BatchRecord;
                await loadCourseOptions();
                form = { ...batch, admission_opens_on: dateOnly(batch.admission_opens_on), admission_closes_on: dateOnly(batch.admission_closes_on), starts_on: dateOnly(batch.starts_on), expected_ends_on: dateOnly(batch.expected_ends_on), actual_ends_on: dateOnly(batch.actual_ends_on), capacity: batch.capacity ?? '' };
            } else if (section === 'subjects') {
                const subject = data as SubjectRecord;
                form = { ...subject };
            } else {
                await loadSubjectOptions();
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

    async function deleteRecord(id: number, label: string, confirm = true) {
        if (confirm && !window.confirm(`Delete ${label}? This action cannot be undone.`)) return;
        setError('');
        setNotice('');
        try {
            const result = await apiRequest<{ message?: string }>(`/api/v1/academy-catalog/${section}/${id}`, { method: 'DELETE' });
            setNotice(result.message ?? `${sectionLabels[section]} deleted.`);
            await reload(section, page);
        } catch (requestError) {
            setError(requestError instanceof Error ? requestError.message : 'Could not delete this record.');
        }
    }

    /** Delete a batch from its course editor, then refresh the parent course details. */
    async function deleteCourseBatch(): Promise<void> {
        if (!pendingBatchDelete) return;

        const target = pendingBatchDelete;
        setPendingBatchDelete(null);
        setError('');
        setNotice('');

        try {
            await apiRequest<{ message?: string }>(`/api/v1/academy-catalog/batches/${target.id}`, { method: 'DELETE' });
            setNotice(`${target.label} deleted.`);
            await reload('courses', page);
            await openEdit(target.courseId);
        } catch (requestError) {
            setError(requestError instanceof Error ? requestError.message : 'Could not delete this batch.');
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
            if (editor.section === 'courses' && editor.id === null) {
                await createCourseFromDialog(editor.form);
                setEditor(null);
                setNotice('Course and selected structure created successfully.');
                await reload('courses', page);
                return;
            }

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
            if (editor.section === 'courses' && editor.id !== null && Array.isArray(payload.stages)) {
                // Stage links are synchronized on the stage endpoint, keeping modules independent.
                const stageAssignments = payload.stages as Array<{ id?: number; subject_ids?: number[] }>;
                await Promise.all(stageAssignments.filter((stage) => stage.id !== undefined).map((stage) =>
                    apiRequest(`/api/v1/academy-catalog/courses/${editor.id}/stages/${stage.id}`, {
                        method: 'PUT',
                        body: JSON.stringify({ subject_ids: stage.subject_ids ?? [] }),
                    }),
                ));
            }
            setEditor(null);
            setNotice(result.message ?? `${sectionLabels[editor.section]} ${editor.id ? 'updated' : 'created'} successfully.`);
            await reload(editor.section, page);
        } catch (requestError) {
            setError(requestError instanceof Error ? requestError.message : 'Could not save this record.');
        } finally {
            setSaving(false);
        }
    }

    /** Create a course and attach existing reusable modules through course or stage pivots. */
    async function createCourseFromDialog(form: FormState): Promise<void> {
        const instituteId = Number(institutes[0]?.id);
        if (!instituteId) throw new Error('No institute is available to own this course.');

        const asMonths = (value: unknown, unit: unknown) => {
            const amount = Number(value);
            return Math.max(1, Math.ceil(unit === 'days' ? amount / 30 : amount));
        };
        const durationMonths = asMonths(form.course_duration, form.course_duration_unit);
        const validityMonths = Math.max(durationMonths, asMonths(form.course_validity, form.course_validity_unit));
        const courseResult = await apiRequest<{ data: CourseRecord }>('/api/v1/academy-catalog/courses', {
            method: 'POST',
            body: JSON.stringify({
                institute_id: instituteId,
                code: form.code,
                name: form.name,
                description: form.description || null,
                duration_months: durationMonths,
                completion_period_months: validityMonths,
                course_mode: form.course_mode ?? 'offline',
                status: form.is_active === false ? 'inactive' : 'active',
                is_available_for_enrollment: form.is_active !== false,
                subject_ids: form.structure_type === 'single' && Array.isArray(form.subject_ids) ? form.subject_ids : [],
            }),
        });
        const course = courseResult.data;

        const stages = form.structure_type === 'semesters' && Array.isArray(form.stages)
            ? form.stages as Array<{ name: string; duration: number; duration_unit: string; subject_ids: number[] }>
            : [];
        for (const [index, stage] of stages.entries()) {
            await apiRequest(`/api/v1/academy-catalog/courses/${course.id}/stages`, {
                method: 'POST',
                body: JSON.stringify({
                    code: `${String(form.code).slice(0, 235)}-SEM-${String(index + 1).padStart(2, '0')}`,
                    name: stage.name,
                    sequence_number: index + 1,
                    duration_months: asMonths(stage.duration, stage.duration_unit),
                    is_mandatory: true,
                    requires_exam: true,
                    certificate_enabled: index === 0 || index === stages.length - 1,
                    is_active: true,
                    subject_ids: stage.subject_ids,
                }),
            });
        }
    }

    function changePage(nextPage: number) {
        setPage(nextPage);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${title} | CMUD`} />
            <main className="min-h-screen bg-[#F7F9FC] px-4 py-6 text-slate-800 sm:px-7 lg:px-10">
                <div className="mx-auto max-w-7xl space-y-6">
                    <header className="relative flex flex-col justify-between gap-4 rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-6 text-white shadow-lg sm:flex-row sm:items-end sm:p-8">
                        <div>
                            <div className="mb-3 flex items-center gap-2 text-xs font-semibold tracking-[0.16em] text-cyan-100 uppercase"><Settings2 size={15} /> Academy workspace</div>
                            <div className="flex flex-wrap items-center gap-3">
                                <h1 className="text-3xl font-semibold tracking-tight">{title}</h1>
                                {section !== 'subjects' && <span className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-sm font-medium" aria-live="polite"><span className="text-cyan-100">Total {title.toLowerCase()}</span><span className="font-semibold text-white">{loading ? '?' : records.total.toLocaleString()}</span></span>}
                            </div>
                            <p className="mt-2 max-w-xl text-sm text-cyan-50/85">Manage {title.toLowerCase()} and their academic records.</p>
                        </div>
                        <button type="button" onClick={openCreate} className={`inline-flex h-11 items-center justify-center gap-2 self-start rounded-xl bg-white px-4 text-sm font-semibold text-[#153B5B] shadow-sm transition hover:bg-cyan-50 sm:self-center ${section === 'courses' ? 'sm:absolute sm:top-1/2 sm:right-8 sm:-translate-y-1/2' : ''}`}><Plus size={17} /> Add {section === 'courses' ? 'course' : sectionLabels[section]}</button>
                    </header>

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
                                        : currentRows.length ? currentRows.map((record) => <ManagementRow key={`${section}-${record.id}`} section={section} record={record} onView={() => void openView(record.id)} onEdit={() => void openEdit(record.id)} onDelete={() => section === 'courses' ? setPendingCourseDelete({ id: record.id, label: recordLabel(section, record) }) : void deleteRecord(record.id, recordLabel(section, record))} />)
                                            : <tr><td colSpan={6} className="px-5 py-16 text-center"><span className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-400"><Layers3 size={20} /></span><p className="mt-3 text-sm font-semibold text-[#17375C]">No {sectionLabels[section]} records found</p><p className="mt-1 text-xs text-slate-500">Add a record to get started, or adjust your search.</p></td></tr>}
                                </tbody>
                            </table>
                        </div>

                        <footer className="flex flex-col justify-between gap-3 border-t border-slate-100 px-5 py-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:px-6">
                            <span>Showing <strong className="text-slate-700">{records.from ?? 0}–{records.to ?? 0}</strong> of <strong className="text-slate-700">{records.total}</strong> {sectionLabels[section]} records</span>
                            <div className="flex items-center gap-1" aria-label="Academy record pages">
                                <button type="button" disabled={loading || records.current_page <= 1} onClick={() => changePage(Math.max(1, records.current_page - 1))} aria-label="Previous page" className="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 text-slate-500 disabled:text-slate-300"><ChevronLeft size={16} /></button>
                                {paginationItems(records.current_page, records.last_page).map((item, index) => item === '…'
                                    ? <span key={`ellipsis-${index}`} className="px-1 text-slate-400" aria-hidden="true">…</span>
                                    : <button key={item} type="button" disabled={loading} aria-current={item === records.current_page ? 'page' : undefined} onClick={() => changePage(item)} className={`grid h-8 min-w-8 place-items-center rounded-lg px-2 ${item === records.current_page ? 'bg-[#1B365D] font-semibold text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50'}`}>{item}</button>)}
                                <button type="button" disabled={loading || records.current_page >= records.last_page} onClick={() => changePage(Math.min(records.last_page, records.current_page + 1))} aria-label="Next page" className="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 text-slate-500 disabled:text-slate-300"><ChevronRight size={16} /></button>
                            </div>
                        </footer>
                    </section>
                </div>
            </main>

            {editor && <EditorModal section={editor.section} id={editor.id} form={editor.form} institutes={institutes} branches={branches} courses={courseOptions} subjects={subjectOptions} saving={saving} error={error} onChange={changeField} onClose={() => setEditor(null)} onDelete={editor.id === null ? undefined : editor.section === 'courses' ? () => setPendingCourseDelete({ id: editor.id!, label: String(editor.form.name ?? 'this course') }) : editor.section === 'subjects' ? () => setPendingSubjectDelete({ id: editor.id!, label: String(editor.form.name ?? 'this module') }) : undefined} onDeleteBatch={(batch) => editor.id !== null && setPendingBatchDelete({ id: batch.id, courseId: editor.id, label: batch.name })} onSubmit={submit} />}
            {pendingCourseDelete && <ConfirmationModal title="Delete course?" message={`Delete “${pendingCourseDelete.label}”? This cannot be undone.`} confirmLabel="Delete course" onCancel={() => setPendingCourseDelete(null)} onConfirm={() => { const target = pendingCourseDelete; setPendingCourseDelete(null); setEditor(null); void deleteRecord(target.id, target.label, false); }} />}
            {pendingSubjectDelete && <ConfirmationModal title="Delete module?" message={`Delete “${pendingSubjectDelete.label}”? Modules assigned to courses, stages, or teachers cannot be deleted.`} confirmLabel="Delete module" onCancel={() => setPendingSubjectDelete(null)} onConfirm={() => { const target = pendingSubjectDelete; setPendingSubjectDelete(null); setEditor(null); void deleteRecord(target.id, target.label, false); }} />}
            {pendingBatchDelete && <ConfirmationModal title="Delete batch?" message={`Delete “${pendingBatchDelete.label}”? This cannot be undone. Batches with enrollments or academic records cannot be deleted.`} confirmLabel="Delete batch" onCancel={() => setPendingBatchDelete(null)} onConfirm={() => void deleteCourseBatch()} />}
            {viewRecord && <RecordDetailsModal section={viewRecord.section} record={viewRecord.record} onClose={() => setViewRecord(null)} />}
        </AppLayout>
    );
}

function tableHeaders(section: Section): string[] {
    switch (section) {
        case 'courses': return ['Course', 'Institute', 'Duration', 'Modules', 'Status'];
        case 'batches': return ['Batch', 'Course', 'Branch', 'Starts', 'Students / capacity', 'Status'];
        case 'subjects': return ['Module / subject', 'Code', 'Status'];
        case 'teachers': return ['Teacher', 'Status', 'Phone', 'Modules / subjects', 'Branches'];
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

/** Summarize the pivot assignments without implying that a module owns a course. */
function subjectAssignmentLabels(subject: SubjectOption): string[] {
    const courseLabels = (subject.courses ?? []).map((course) => course.name);
    const stageLabels = (subject.course_stages ?? []).map((stage) =>
        `${stage.course?.name ?? 'Course'} · ${stage.name}`,
    );

    return [...new Set([...courseLabels, ...stageLabels])];
}

function ManagementRow({ section, record, onView, onEdit, onDelete }: { section: Section; record: CourseRecord | BatchRecord | SubjectRecord | TeacherRecord; onView: () => void; onEdit: () => void; onDelete: () => void }) {
    const cells: ReactNode[] = [];
    if (section === 'courses') {
        const course = record as CourseRecord;
        cells.push(<CellTitle key="title" title={course.name} subtitle={course.code} />, <span key="institute" className="text-sm text-slate-600">{course.institute?.name ?? '—'}</span>, <span key="duration" className="text-sm text-slate-600">{course.duration_months} mo · max {course.completion_period_months} mo</span>, <span key="modules" className="inline-flex items-center gap-1.5 text-sm text-slate-600"><BookOpen size={14} /> {course.subjects?.length ?? 0}</span>, <StatusBadge key="status" active={course.is_active} />);
    } else if (section === 'batches') {
        const batch = record as BatchRecord;
        const capacity = batch.capacity === null ? 'Unlimited' : batch.capacity;
        cells.push(<CellTitle key="title" title={batch.name} subtitle={batch.code} />, <span key="course" className="text-sm text-slate-600">{batch.course?.name ?? '—'}</span>, <span key="branch" className="text-sm text-slate-600">{batch.branch?.name ?? '—'}</span>, <span key="starts" className="text-sm text-slate-600">{batch.starts_on}</span>, <span key="enrollment-count" className="text-sm font-medium text-slate-700">{batch.enrolled_students_count ?? 0}<span className="text-slate-400"> / {capacity}</span></span>, <StatusBadge key="status" label={batch.status} active={batch.status === 'active' || batch.status === 'open'} />);
    } else if (section === 'subjects') {
        const subject = record as SubjectRecord;
        cells.push(<CellTitle key="title" title={subject.name} subtitle={subject.description ?? 'Independent module'} />, <span key="code" className="font-mono text-xs text-slate-600">{subject.code}</span>, <StatusBadge key="status" active={subject.is_active} />);
    } else {
        const teacher = record as TeacherRecord;
        cells.push(<CellTitle key="title" title={teacher.full_name} subtitle={teacher.teacher_code} />, <StatusBadge key="status" active={teacher.is_active} />, <span key="phone" className="text-sm text-slate-600">{teacher.phone}</span>, <span key="subjects" className="text-sm text-slate-600">{teacher.subjects?.length ?? 0} modules / subjects</span>, <span key="branches" className="text-sm text-slate-600">{teacher.branches?.length ?? 0} branches</span>);
    }

    const iconOnlyView = true;
    return <tr className="transition hover:bg-slate-50/70">{cells.map((cell, index) => <td key={index} className="px-5 py-4">{cell}</td>)}<td className="px-5 py-4"><div className="flex justify-end gap-1"><button type="button" onClick={onView} aria-label="View record" title="View" className={`grid h-8 w-8 place-items-center rounded-lg text-[#087586] hover:bg-cyan-50 ${iconOnlyView ? '' : 'inline-flex gap-1 px-2 text-xs font-semibold'}`}><Eye size={14} />{!iconOnlyView && 'View'}</button><button type="button" onClick={onEdit} aria-label="Edit record" title="Edit" className="grid h-8 w-8 place-items-center rounded-lg text-slate-500 hover:bg-cyan-50 hover:text-[#087586]"><Pencil size={15} /></button>{section !== 'courses' && section !== 'subjects' && <button type="button" onClick={onDelete} aria-label="Delete record" title="Delete" className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600"><Trash2 size={15} /></button>}</div></td></tr>;
}

/** Read-only detail dialog showing the selected record and its linked records. */
function RecordDetailsModal({ section, record, onClose }: { section: Section; record: CourseRecord | BatchRecord | SubjectRecord | TeacherRecord; onClose: () => void }) {
    const details: Array<[string, ReactNode]> = [];
    if (section === 'courses') {
        const course = record as CourseRecord;
        details.push(['Course code', course.code], ['Name', course.name], ['Institute', course.institute?.name ?? '—'], ['Mode', course.course_mode], ['Duration', `${course.duration_months} months`], ['Completion period', `${course.completion_period_months} months`], ['Status', course.is_active ? 'Active' : 'Inactive'], ['Description', course.description || '—']);
    } else if (section === 'batches') {
        const batch = record as BatchRecord;
        details.push(['Batch code', batch.code], ['Name', batch.name], ['Course', batch.course ? `${batch.course.name} (${batch.course.code})` : '—'], ['Branch', batch.branch?.name ?? '—'], ['Status', batch.status], ['Enrolled students', String(batch.enrolled_students_count ?? 0)], ['Capacity', batch.capacity?.toString() ?? 'Unlimited'], ['Admission opens', dateOnly(batch.admission_opens_on) || '—'], ['Admission closes', dateOnly(batch.admission_closes_on) || '—'], ['Starts', dateOnly(batch.starts_on)], ['Expected end', dateOnly(batch.expected_ends_on)], ['Actual end', dateOnly(batch.actual_ends_on) || '—']);
    } else if (section === 'subjects') {
        const subject = record as SubjectRecord;
        details.push(['Module code', subject.code], ['Name', subject.name], ['Status', subject.is_active ? 'Active' : 'Inactive'], ['Description', subject.description || '—'], ['Assigned teachers', subject.teachers?.map((teacher) => teacher.full_name).join(', ') || 'None']);
    } else {
        const teacher = record as TeacherRecord;
        details.push(['Teacher code', teacher.teacher_code], ['Name', teacher.full_name], ['Institute', teacher.institute?.name ?? '—'], ['Phone', teacher.phone], ['Email', teacher.email || '—'], ['Qualification', teacher.qualification || '—'], ['Specialization', teacher.specialization || '—'], ['Designation', teacher.designation || '—'], ['Organization', teacher.organization || '—'], ['BMDC number', teacher.bmdc_number || '—'], ['Status', teacher.is_active ? 'Active' : 'Inactive'], ['Branches', teacher.branches?.map((branch) => branch.name).join(', ') || 'None'], ['Notes', teacher.notes || '—']);
    }

    return <div className="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto bg-[#10233B]/55 p-4 backdrop-blur-[1px]" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
        <section role="dialog" aria-modal="true" aria-labelledby="academy-record-title" className="my-auto max-h-[calc(100vh-2rem)] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <header className="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white px-6 py-5"><div><p className="text-[10px] font-bold tracking-[0.15em] text-[#087586] uppercase">Academy record</p><h2 id="academy-record-title" className="mt-1 text-lg font-semibold text-[#17375C]">{recordLabel(section, record)}</h2></div><button type="button" onClick={onClose} aria-label="Close record details" className="rounded-lg p-2 text-slate-400 hover:bg-slate-100"><X size={18} /></button></header>
            <dl className="grid gap-4 p-6 sm:grid-cols-2">{details.map(([label, value]) => <div key={label} className="min-w-0 rounded-xl border border-slate-100 bg-slate-50/70 p-3"><dt className="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{label}</dt><dd className="mt-1.5 break-words text-sm text-slate-700">{value}</dd></div>)}</dl>
            {section === 'subjects' && <SubjectCourseAssignments subject={record as SubjectRecord} />}
            {section === 'courses' && <CourseRelationships course={record as CourseRecord} />}
            {section === 'teachers' && <TeacherSubjectAssignments teacher={record as TeacherRecord} />}
            <footer className="flex justify-end border-t border-slate-100 px-6 py-4"><button type="button" onClick={onClose} className="rounded-lg bg-[#17375C] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#254878]">Close</button></footer>
        </section>
    </div>;
}

/** Shows the reusable module's direct course links and semester/stage links in its view dialog. */
function SubjectCourseAssignments({ subject }: { subject: SubjectRecord }) {
    const courses = subject.courses ?? [];
    const stages = subject.course_stages ?? [];
    const hasAssignments = courses.length > 0 || stages.length > 0;

    return <section aria-labelledby="subject-course-assignments-title" className="mx-6 mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <header className="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-4 py-3">
            <div className="flex items-center gap-2"><BookOpen size={16} className="text-[#087586]" /><h3 id="subject-course-assignments-title" className="text-sm font-semibold text-[#17375C]">Assigned courses and stages</h3></div>
            <span className="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-[#087586]">{new Set([...courses.map((course) => course.id), ...stages.map((stage) => stage.course?.id).filter((id): id is number => id !== undefined)]).size}</span>
        </header>
        {hasAssignments ? <div className="grid gap-4 p-4 sm:grid-cols-2">
            {courses.length > 0 && <div><h4 className="mb-2 text-xs font-semibold text-slate-500">Direct course assignments</h4><ul className="space-y-2">{courses.map((course) => <li key={course.id} className="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2"><p className="text-sm font-medium text-slate-700">{course.name}</p><p className="mt-0.5 font-mono text-[11px] text-slate-500">{course.code}</p></li>)}</ul></div>}
            {stages.length > 0 && <div><h4 className="mb-2 text-xs font-semibold text-slate-500">Stage / semester assignments</h4><ul className="space-y-2">{stages.map((stage) => <li key={stage.id} className="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2"><p className="text-sm font-medium text-slate-700">{stage.course?.name ?? 'Course'} · {stage.name}</p><p className="mt-0.5 font-mono text-[11px] text-slate-500">{stage.course?.code ?? 'Course code unavailable'} · {stage.code}</p></li>)}</ul></div>}
        </div> : <p className="px-4 py-5 text-sm text-slate-500">This module is not currently assigned to a course or stage.</p>}
    </section>;
}

/** Presents a course's linked modules and batches as separate, scannable lists. */
function CourseRelationships({ course }: { course: CourseRecord }) {
    const stages = [...(course.stages ?? [])].sort((left, right) => left.sequence_number - right.sequence_number);
    const modules = [...new Map([...(course.subjects ?? []), ...stages.flatMap((stage) => stage.subjects ?? [])].map((module) => [module.id, module])).values()];
    const batches = (course.batches ?? []).filter((batch) => ['active', 'open', 'upcoming', 'ongoing'].includes(batch.status.toLowerCase()));

    return <div className="space-y-5 px-6 pb-6">
        <section aria-labelledby="course-modules-title" className="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <header className="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-4 py-3">
                <div className="flex items-center gap-2"><BookOpen size={16} className="text-[#087586]" /><h3 id="course-modules-title" className="text-sm font-semibold text-[#17375C]">Modules / subjects</h3></div>
                <span className="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-[#087586]">{modules.length}</span>
            </header>
            {stages.length ? <div className="space-y-3 p-4">{stages.map((stage) => <section key={stage.id} className="rounded-xl border border-slate-200 bg-white">
                <header className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/60 px-3 py-2.5"><div><h4 className="text-sm font-semibold text-[#17375C]">{stage.name}</h4><p className="mt-0.5 text-xs text-slate-500">{stage.duration_months} months · {stage.subjects.length} modules</p></div><span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${stage.certificate_enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>{stage.certificate_enabled ? 'Certificate available' : 'No certificate'}</span></header>
                {stage.subjects.length ? <ul className="divide-y divide-slate-100">{stage.subjects.map((module) => <li key={module.id} className="flex items-center justify-between gap-3 px-3 py-2.5"><span className="min-w-0 truncate text-sm font-medium text-slate-700">{module.name}</span><span className="shrink-0 rounded-md bg-slate-100 px-2 py-1 font-mono text-[11px] text-slate-500">{module.code}</span></li>)}</ul> : <p className="px-3 py-3 text-sm text-slate-500">No modules assigned to this semester.</p>}
            </section>)}</div> : modules.length ? <ul className="divide-y divide-slate-100">{modules.map((module) => <li key={module.id} className="flex items-center justify-between gap-3 px-4 py-3"><span className="min-w-0 truncate text-sm font-medium text-slate-700">{module.name}</span><span className="shrink-0 rounded-md bg-slate-100 px-2 py-1 font-mono text-[11px] text-slate-500">{module.code}</span></li>)}</ul> : <p className="px-4 py-5 text-sm text-slate-500">No modules or subjects are linked to this course yet.</p>}
        </section>
        <section aria-labelledby="course-batches-title" className="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <header className="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-4 py-3">
                <div className="flex items-center gap-2"><CalendarDays size={16} className="text-[#087586]" /><h3 id="course-batches-title" className="text-sm font-semibold text-[#17375C]">Batches</h3></div>
                <span className="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-[#087586]">{batches.length}</span>
            </header>
            {batches.length ? <ul className="divide-y divide-slate-100">{batches.map((batch) => <li key={batch.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div className="min-w-0"><p className="truncate text-sm font-medium text-slate-700">{batch.name}</p><p className="mt-1 text-xs text-slate-500">{batch.code}{batch.branch?.name ? ` · ${batch.branch.name}` : ''}{batch.starts_on ? ` · Starts ${dateOnly(batch.starts_on)}` : ''}</p></div>
                <div className="flex items-center gap-2"><span className="text-xs text-slate-500">{batch.enrolled_students_count ?? 0} students / {batch.capacity != null ? `${batch.capacity} seats` : 'No capacity limit'}</span><StatusBadge label={batch.status} active={batch.status === 'active' || batch.status === 'open'} /></div>
            </li>)}</ul> : <p className="px-4 py-5 text-sm text-slate-500">No batches have been created for this course yet.</p>}
        </section>
    </div>;
}

/** Lists every course batch in the edit dialog and exposes its confirmed delete action. */
function CourseBatchManager({ batches, onDelete }: { batches: BatchRecord[]; onDelete: (batch: BatchRecord) => void }) {
    return <section aria-labelledby="course-batch-management-title" className="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <header className="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-4 py-3">
            <div><h3 id="course-batch-management-title" className="text-sm font-semibold text-[#17375C]">Course batches</h3><p className="mt-1 text-xs text-slate-500">Delete a batch here. Batches with enrollments or academic records are protected.</p></div>
            <span className="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-[#087586]">{batches.length}</span>
        </header>
        {batches.length ? <ul className="divide-y divide-slate-100">{batches.map((batch) => <li key={batch.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div className="min-w-0"><p className="truncate text-sm font-medium text-slate-700">{batch.name}</p><p className="mt-1 text-xs text-slate-500">{batch.code} · {batch.status} · {batch.enrolled_students_count ?? 0} enrolled · {batch.capacity == null ? 'No capacity limit' : `Capacity ${batch.capacity}`}</p></div>
            <button type="button" onClick={() => onDelete(batch)} aria-label={`Delete batch ${batch.name}`} title="Delete batch" className="grid h-9 w-9 place-items-center rounded-lg text-rose-600 hover:bg-rose-50"><Trash2 size={16} /></button>
        </li>)}</ul> : <p className="px-4 py-5 text-sm text-slate-500">No batches are assigned to this course.</p>}
    </section>;
}

/** Shows the independent modules assigned to a teacher and their course placements. */
function TeacherSubjectAssignments({ teacher }: { teacher: TeacherRecord }) {
    const assignedSubjects = teacher.subjects ?? [];

    return <section aria-labelledby="teacher-subject-assignments-title" className="px-6 pb-6">
        <div className="mb-3 flex items-center justify-between gap-3"><div><h3 id="teacher-subject-assignments-title" className="text-sm font-semibold text-[#17375C]">Assigned modules / subjects</h3><p className="mt-1 text-xs text-slate-500">These are the subjects assigned directly to this teacher.</p></div><span className="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-[#087586]">{assignedSubjects.length} assigned</span></div>
        {assignedSubjects.length ? <ul className="grid gap-3 sm:grid-cols-2">{assignedSubjects.map((subject) => <li key={subject.id} className="rounded-xl border border-slate-200 bg-white p-4"><div className="flex items-start justify-between gap-3"><div className="min-w-0"><p className="truncate text-sm font-semibold text-slate-700">{subject.name}</p><p className="mt-1 font-mono text-[11px] text-slate-500">{subject.code}</p><p className="mt-2 text-xs text-slate-500">Assigned in: {subjectAssignmentLabels(subject).join(', ') || 'No course or stage yet'}</p></div><StatusBadge label={subject.pivot.is_active ? 'Active' : 'Inactive'} active={subject.pivot.is_active} /></div>{subject.pivot.teaching_role && <p className="mt-3 text-xs text-slate-600">Teaching role: {subject.pivot.teaching_role}</p>}</li>)}</ul> : <p className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">No modules or subjects are assigned to this teacher.</p>}
    </section>;
}

function EditorModal({ section, id, form, institutes, branches, courses, subjects, saving, error, onChange, onClose, onDelete, onDeleteBatch, onSubmit }: { section: Section; id: number | null; form: FormState; institutes: InstituteOption[]; branches: BranchOption[]; courses: CourseRecord[]; subjects: SubjectOption[]; saving: boolean; error: string; onChange: (name: string, value: unknown) => void; onClose: () => void; onDelete?: () => void; onDeleteBatch: (batch: BatchRecord) => void; onSubmit: (event: FormEvent<HTMLFormElement>) => void }) {
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

    function toggleTeacherSubject(subjectId: number, checked: boolean) {
        const current = Array.isArray(form.subject_assignments) ? form.subject_assignments as SubjectAssignment[] : [];
        const next = checked
            ? [...current, { subject_id: subjectId, teaching_role: '', is_active: true, notes: '' }]
            : current.filter((assignment) => assignment.subject_id !== subjectId);
        onChange('subject_assignments', next);
    }

    function updateTeacherSubject(subjectId: number, field: keyof SubjectAssignment, value: string | boolean) {
        const current = Array.isArray(form.subject_assignments) ? form.subject_assignments as SubjectAssignment[] : [];
        onChange('subject_assignments', current.map((assignment) => assignment.subject_id === subjectId ? { ...assignment, [field]: value } : assignment));
    }

    return <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-[#10233B]/55 p-4 backdrop-blur-[1px]" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
        <section role="dialog" aria-modal="true" aria-labelledby="academy-editor-title" className="my-auto max-h-[calc(100vh-2rem)] w-full max-w-3xl overflow-y-auto rounded-2xl bg-[#F8FAFC] shadow-2xl">
            <header className="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white px-6 py-5"><div><p className="text-[10px] font-bold tracking-[0.15em] text-[#087586] uppercase">Academy catalog</p><h2 id="academy-editor-title" className="mt-1 text-lg font-semibold text-[#17375C]">{title}</h2></div><button type="button" onClick={onClose} aria-label="Close form" className="rounded-lg p-2 text-slate-400 hover:bg-slate-100"><X size={18} /></button></header>
            <form onSubmit={onSubmit} className="space-y-5 p-6">
                {error && <p role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</p>}
                {section === 'courses' && <>
                    <CourseSetupForm form={form} institutes={institutes} subjects={subjects} select={select} input={input} onChange={onChange} toggleId={toggleId} />
                    {id !== null && <CourseBatchManager batches={Array.isArray(form.batches) ? form.batches as BatchRecord[] : []} onDelete={onDeleteBatch} />}
                </>}
                {section === 'batches' && <div className="grid gap-4 sm:grid-cols-2">
                    <div className="rounded-xl border border-cyan-100 bg-cyan-50/60 p-4 sm:col-span-2">{select('course_id', 'Assign batch to course', courses, true, () => onChange('branch_id', ''))}<p className="mt-2 text-xs text-slate-600">The batch belongs to this course. Its branch options are limited to the course’s institute.</p></div>{select('branch_id', 'Branch', batchBranches, true, undefined, !selectedBatchCourse)}{input('code', 'Batch code', 'text', true)}{input('name', 'Batch name', 'text', true)}{input('admission_opens_on', 'Admission opens', 'date')}{input('admission_closes_on', 'Admission closes', 'date')}{input('starts_on', 'Starts on', 'date', true)}{input('expected_ends_on', 'Expected end date', 'date', true)}{input('actual_ends_on', 'Actual end date', 'date')}{input('capacity', 'Student capacity (blank means unlimited)', 'number', false, { min: '1' })}
                    <SelectTextField label="Batch status" value={String(form.status ?? 'active')} options={['active', 'open', 'upcoming', 'ongoing', 'inactive', 'completed', 'cancelled']} onChange={(value) => onChange('status', value)} />
                    <div className="sm:col-span-2">{input('notes', 'Notes')}</div>
                </div>}
                {section === 'subjects' && <div className="grid gap-4 sm:grid-cols-2">
                    <p className="rounded-lg bg-cyan-50 px-3 py-2 text-xs text-cyan-900 sm:col-span-2">Modules are independent records. Assign them to courses or semesters from course setup.</p>{input('code', 'Module code', 'text', true)}{input('name', 'Module / subject name', 'text', true)}<div className="sm:col-span-2">{input('description', 'Description')}</div>
                    <SelectTextField label="Subject status" value={form.is_active === false ? 'inactive' : 'active'} options={['active', 'inactive']} onChange={(value) => onChange('is_active', value === 'active')} />
                </div>}
                {section === 'teachers' && <div className="grid gap-4 sm:grid-cols-2">
                    {select('institute_id', 'Institute', institutes, true)}{input('teacher_code', 'Teacher code', 'text', true)}{input('full_name', 'Full name', 'text', true)}{input('phone', 'Phone', 'tel', true)}{input('email', 'Email', 'email')}{input('qualification', 'Qualification')}{input('specialization', 'Specialization')}{input('designation', 'Designation')}{input('organization', 'Organization')}{input('bmdc_number', 'BMDC number')}<div className="sm:col-span-2">{input('address', 'Address')}</div><div className="sm:col-span-2">{input('notes', 'Notes')}</div>
                    <div className="sm:col-span-2"><CheckList title="Branch assignments" options={branches} selected={Array.isArray(form.branch_ids) ? form.branch_ids as number[] : []} onToggle={(branchId, checked) => toggleId('branch_ids', branchId, checked)} emptyText="No branches are available." /></div>
                    <div className="sm:col-span-2"><SearchableMultiSelect title="Assigned modules / subjects" options={subjects} selected={Array.isArray(form.subject_assignments) ? (form.subject_assignments as SubjectAssignment[]).map((assignment) => assignment.subject_id) : []} onToggle={toggleTeacherSubject} emptyText="Create modules / subjects first to assign them to a teacher." /></div>
                    {Array.isArray(form.subject_assignments) && (form.subject_assignments as SubjectAssignment[]).map((assignment) => {
                        const module = subjects.find((subject) => subject.id === assignment.subject_id);
                        return <div key={assignment.subject_id} className="sm:col-span-2 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2">
                            <div className="sm:col-span-2"><p className="text-sm font-semibold text-slate-700">{module?.name ?? 'Selected module'}</p><p className="mt-1 text-xs text-slate-500">{module ? subjectAssignmentLabels(module).join(', ') || 'Independent module' : 'Module unavailable'}{module?.code ? ` · ${module.code}` : ''}</p></div>
                            <FormInput name={`teaching_role_${assignment.subject_id}`} label="Teaching role" type="text" required={false} value={assignment.teaching_role} extra={{}} onChange={(value) => updateTeacherSubject(assignment.subject_id, 'teaching_role', value)} />
                            <BooleanField label="Assignment is active" checked={assignment.is_active} onChange={(checked) => updateTeacherSubject(assignment.subject_id, 'is_active', checked)} />
                            <div className="sm:col-span-2"><FormInput name={`assignment_notes_${assignment.subject_id}`} label="Assignment notes" type="text" required={false} value={assignment.notes} extra={{}} onChange={(value) => updateTeacherSubject(assignment.subject_id, 'notes', value)} /></div>
                        </div>;
                    })}
                    <div className="sm:col-span-2"><BooleanField label="Teacher is active" checked={Boolean(form.is_active)} onChange={(checked) => onChange('is_active', checked)} /></div>
                </div>}
                <footer className="sticky bottom-0 flex justify-between gap-2 border-t border-slate-200 bg-[#F8FAFC] pt-4">{onDelete ? <button type="button" onClick={onDelete} className="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50"><Trash2 size={15} /> Delete {section === 'courses' ? 'course' : 'module'}</button> : <span /> }<div className="flex gap-2"><button type="button" onClick={onClose} className="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button type="submit" disabled={saving} className="inline-flex items-center gap-2 rounded-lg bg-[#17375C] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#254878] disabled:opacity-60">{saving && <LoaderCircle size={15} className="animate-spin" />}{saving ? 'Saving...' : id ? 'Save changes' : 'Create record'}</button></div></footer>
            </form>
        </section>
    </div>;
}

/** UI-only course structure builder; these draft fields are not persisted yet. */
function CourseSetupForm({ form, institutes, subjects, select, input, onChange, toggleId }: {
    form: FormState;
    institutes: InstituteOption[];
    subjects: SubjectOption[];
    select: (name: string, label: string, options: Array<{ id: number | string; name: string }>, required?: boolean) => ReactNode;
    input: (name: string, label: string, type?: string, required?: boolean, extra?: Record<string, string>) => ReactNode;
    onChange: (name: string, value: unknown) => void;
    toggleId: (field: string, idValue: number, checked: boolean) => void;
}) {
    const stages = Array.isArray(form.stages) ? form.stages as Array<{ name: string; duration: number; duration_unit: string; subject_ids: number[] }> : [];
        const modules = subjects;
    const setStage = (index: number, patch: Partial<(typeof stages)[number]>) => onChange('stages', stages.map((stage, current) => current === index ? { ...stage, ...patch } : stage));
    const durationField = (field: string, unitField: string, label: string) => <div className="grid grid-cols-[1fr_120px] gap-2">
        <label className="block text-xs font-medium text-slate-600">{label}<input type="number" min="1" required value={String(form[field] ?? 1)} onChange={(event) => onChange(field, Number(event.target.value))} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm" /></label>
        <SelectTextField label="Unit" value={String(form[unitField] ?? 'months')} options={['days', 'months']} onChange={(value) => onChange(unitField, value)} />
    </div>;

    return <div className="grid gap-5">
        <div className="grid gap-4 sm:grid-cols-2">
            {input('code', 'Course code', 'text', true)}{input('name', 'Course name', 'text', true)}
            {durationField('course_duration', 'course_duration_unit', 'Course duration')}{durationField('course_validity', 'course_validity_unit', 'Course validity')}
            <SelectTextField label="Course mode" value={String(form.course_mode ?? 'offline')} options={['online', 'offline', 'dual']} onChange={(value) => onChange('course_mode', value)} />
            <div className="sm:col-span-2">{input('description', 'Description')}</div>
        </div>
        <section className="rounded-2xl border border-slate-200 bg-white p-4">
            <h3 className="text-sm font-semibold text-[#17375C]">Course structure</h3>
            <p className="mt-1 text-xs text-slate-500">Choose semesters with reusable modules assigned to each, or assign modules directly to a course without semesters.</p>
            <div className="mt-3 flex gap-2">{[['semesters', 'Multiple semesters'], ['single', 'No semesters']].map(([value, label]) => <button key={value} type="button" onClick={() => onChange('structure_type', value)} className={`rounded-lg border px-3 py-2 text-xs font-semibold ${form.structure_type === value ? 'border-[#087586] bg-cyan-50 text-[#087586]' : 'border-slate-200 text-slate-600'}`}>{label}</button>)}</div>
        </section>
        {form.structure_type === 'semesters' ? <div className="space-y-3">
            {stages.map((stage, index) => <section key={index} className="rounded-2xl border border-slate-200 bg-white p-4">
                <div className="flex items-center justify-between"><h4 className="text-sm font-semibold text-[#17375C]">Semester {index + 1}</h4>{stages.length > 1 && <button type="button" onClick={() => onChange('stages', stages.filter((_, current) => current !== index))} className="text-xs text-rose-600">Remove</button>}</div>
                <div className="mt-3 grid gap-3 sm:grid-cols-2"><FormInput name={`stage-${index}`} label="Semester name" type="text" required value={stage.name} extra={{}} onChange={(value) => setStage(index, { name: value })} />
                    <div className="grid grid-cols-[1fr_120px] gap-2"><FormInput name={`stage-duration-${index}`} label="Semester duration" type="number" required value={String(stage.duration)} extra={{ min: '1' }} onChange={(value) => setStage(index, { duration: Number(value) })} /><SelectTextField label="Unit" value={stage.duration_unit} options={['days', 'months']} onChange={(value) => setStage(index, { duration_unit: value })} /></div>
                    <div className="sm:col-span-2"><CheckList title="Modules in this semester" options={modules} selected={stage.subject_ids} onToggle={(subjectId, checked) => setStage(index, { subject_ids: checked ? [...stage.subject_ids, subjectId] : stage.subject_ids.filter((id) => id !== subjectId) })} emptyText="No modules loaded. They can be assigned after the course is created." /></div>
                </div>
            </section>)}
            <button type="button" onClick={() => onChange('stages', [...stages, { name: `Semester ${stages.length + 1}`, duration: 1, duration_unit: 'months', subject_ids: [] }])} className="rounded-lg border border-dashed border-[#087586]/50 px-3 py-2 text-xs font-semibold text-[#087586]">+ Add semester</button>
        </div> : <CheckList title="Course modules / subjects" options={modules} selected={Array.isArray(form.subject_ids) ? form.subject_ids as number[] : []} onToggle={(id, checked) => toggleId('subject_ids', id, checked)} emptyText="No modules loaded. They can be assigned after the course is created." />}
        <p className="rounded-lg bg-cyan-50 px-3 py-2 text-xs text-cyan-900">Module selection links existing modules to the course or semester. It does not create duplicate module records.</p>
        <BooleanField label="Course is active" checked={Boolean(form.is_active)} onChange={(checked) => onChange('is_active', checked)} />
    </div>;
}

function ConfirmationModal({ title, message, confirmLabel, onCancel, onConfirm }: { title: string; message: string; confirmLabel: string; onCancel: () => void; onConfirm: () => void }) {
    return <div className="fixed inset-0 z-[80] flex items-center justify-center bg-[#10233B]/60 p-4 backdrop-blur-sm" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onCancel(); }}>
        <section role="alertdialog" aria-modal="true" aria-labelledby="confirm-delete-title" className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <h2 id="confirm-delete-title" className="text-lg font-semibold text-[#17375C]">{title}</h2>
            <p className="mt-2 text-sm leading-6 text-slate-600">{message}</p>
            <div className="mt-6 flex justify-end gap-2"><button type="button" onClick={onCancel} className="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button type="button" onClick={onConfirm} className="rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-700">{confirmLabel}</button></div>
        </section>
    </div>;
}

function FormInput({ name, label, type, required, value, extra, onChange }: { name: string; label: string; type: string; required: boolean; value: string; extra: Record<string, string>; onChange: (value: string) => void }) {
    return <label className="block text-xs font-medium text-slate-600">{label}{required && <span className="ml-1 text-rose-500">*</span>}<input id={name} name={name} type={type} required={required} value={value} onChange={(event) => onChange(event.target.value)} {...extra} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-[#16828D] focus:ring-3 focus:ring-[#16828D]/10" /></label>;
}

function SelectTextField({ label, value, options, onChange }: { label: string; value: string; options: string[]; onChange: (value: string) => void }) {
    return <label className="block text-xs font-medium text-slate-600">{label}<select value={value} onChange={(event) => onChange(event.target.value)} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-[#16828D]">{options.map((option) => <option key={option} value={option}>{option.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())}</option>)}</select></label>;
}

function CheckList({ title, options, selected, onToggle, emptyText }: { title: string; options: PickerOption[]; selected: number[]; onToggle: (id: number, checked: boolean) => void; emptyText: string }) {
    return <div className="rounded-xl border border-slate-200 bg-white p-4"><h3 className="text-xs font-semibold text-slate-700">{title}</h3>{options.length ? <div className="mt-3 grid max-h-48 gap-2 overflow-y-auto sm:grid-cols-2">{options.map((option) => <label key={option.id} className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-slate-600 hover:bg-slate-50"><input type="checkbox" checked={selected.includes(option.id)} onChange={(event) => onToggle(option.id, event.target.checked)} className="rounded border-slate-300 text-[#087586] focus:ring-[#087586]" /><span className="min-w-0 truncate">{option.name}</span>{option.code && <span className="ml-auto shrink-0 font-mono text-[10px] text-slate-400">{option.code}</span>}</label>)}</div> : <p className="mt-2 text-xs text-slate-400">{emptyText}</p>}</div>;
}

/** Searchable multi-select keeps the module catalog manageable during teacher allocation. */
function SearchableMultiSelect({ title, options, selected, onToggle, emptyText }: { title: string; options: PickerOption[]; selected: number[]; onToggle: (id: number, checked: boolean) => void; emptyText: string }) {
    const [search, setSearch] = useState('');
    const [open, setOpen] = useState(false);
    const pickerRef = useRef<HTMLDivElement>(null);
    const matchingOptions = options.filter((option) => `${option.name} ${option.code ?? ''} ${option.course?.name ?? ''} ${option.course?.code ?? ''}`.toLowerCase().includes(search.trim().toLowerCase()));
    const selectedOptions = options.filter((option) => selected.includes(option.id));

    useEffect(() => {
        if (!open) return;

        // Close the results when a pointer action happens outside this picker.
        function closeOnOutsidePointer(event: PointerEvent) {
            if (event.target instanceof Node && !pickerRef.current?.contains(event.target)) setOpen(false);
        }

        document.addEventListener('pointerdown', closeOnOutsidePointer);
        return () => document.removeEventListener('pointerdown', closeOnOutsidePointer);
    }, [open]);

    return <div ref={pickerRef} className="rounded-xl border border-slate-200 bg-white p-4">
        <div className="flex items-center justify-between gap-3"><div><h3 className="text-xs font-semibold text-slate-700">{title}</h3><p className="mt-1 text-[11px] text-slate-500">Search and select one or more modules.</p></div><span className="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">{selected.length} selected</span></div>
        <div className="relative mt-3">
            <Search size={15} className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-slate-400" />
            <input type="search" value={search} onFocus={() => setOpen(true)} onChange={(event) => { setSearch(event.target.value); setOpen(true); }} onKeyDown={(event) => { if (event.key === 'Escape') setOpen(false); if (event.key === 'Enter' && matchingOptions[0]) { event.preventDefault(); onToggle(matchingOptions[0].id, !selected.includes(matchingOptions[0].id)); } }} placeholder="Search modules by name or code" aria-label={`Search ${title.toLowerCase()}`} aria-expanded={open} className="h-10 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-9 text-sm outline-none placeholder:text-slate-400 focus:border-[#16828D] focus:ring-3 focus:ring-[#16828D]/10" />
            {open && <div className="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg" role="listbox" aria-multiselectable="true" aria-label={title}>
                {matchingOptions.length ? matchingOptions.map((option) => {
                    const isSelected = selected.includes(option.id);
                    return <button key={option.id} type="button" role="option" aria-selected={isSelected} onMouseDown={(event) => event.preventDefault()} onClick={() => onToggle(option.id, !isSelected)} className="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-cyan-50 focus:bg-cyan-50 focus:outline-none">
                        <span className={`grid h-4 w-4 shrink-0 place-items-center rounded border ${isSelected ? 'border-[#087586] bg-[#087586] text-white' : 'border-slate-300 text-transparent'}`}><Check size={12} /></span>
                        <span className="min-w-0 flex-1"><span className="block truncate text-sm text-slate-700">{option.name}</span>{option.course && <span className="block truncate text-[10px] text-slate-400">{option.course.name}</span>}</span><span className="shrink-0 font-mono text-[10px] text-slate-400">{option.code}</span>
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

