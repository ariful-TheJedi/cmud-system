import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    CircleHelp,
    GraduationCap,
    Search,
    SlidersHorizontal,
    UserPlus,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';

type Student = {
    id: number;
    code: string;
    name: string;
    phone: string;
    course: string;
    branch: string;
    batch: string;
    status: string;
    admitted: string;
    initials: string;
    color: string;
};

type StudentApiRecord = {
    id: number;
    student_code: string;
    full_name: string;
    phone: string;
    status: string;
    admission_date: string | null;
    admission_branch: { id: number; name: string } | null;
    latest_enrollment: {
        course: { name: string } | null;
        branch: { name: string } | null;
        batch: { name: string } | null;
    } | null;
};

type StudentPage = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type StudentApiResponse = {
    data: StudentApiRecord[];
    meta: StudentPage;
    filters?: StudentFilterOptions;
};

type FilterOption = { id: number; name: string; course_id?: number };
type StudentFilterOptions = {
    courses: FilterOption[];
    batches: FilterOption[];
    branches: FilterOption[];
    stages: FilterOption[];
};

const emptyFilterOptions: StudentFilterOptions = {
    courses: [],
    batches: [],
    branches: [],
    stages: [],
};

type PaymentRecord = {
    payment_number: string;
    amount: string;
    paid_at: string | null;
    status: string;
    transaction_reference: string | null;
    payment_method: string | null;
};

type TuitionRecord = {
    tuition_number: string;
    original_amount: string;
    waiver_amount: string;
    issued_on: string;
    due_on: string | null;
    status: string;
    payments: PaymentRecord[];
};

type EnrollmentRecord = {
    enrollment_code: string;
    enrollment_date: string;
    starts_on: string | null;
    expected_completion_on: string;
    completed_on: string | null;
    status: string;
    is_active: boolean;
    course: { code: string; name: string } | null;
    branch: { name: string } | null;
    batch: { name: string } | null;
    tuition: TuitionRecord | null;
};

type ExpenseRecord = {
    expense_number: string;
    description: string;
    quantity: string;
    unit_amount: string;
    waiver_amount: string;
    issued_on: string;
    due_on: string | null;
    status: string;
    expense_type: string | null;
    branch: string | null;
    payments: PaymentRecord[];
};

type StudentDetails = {
    student_code: string;
    full_name: string;
    father_name: string | null;
    mother_name: string | null;
    date_of_birth: string | null;
    gender: string | null;
    phone: string;
    alternative_phone: string | null;
    phone_numbers: string[] | null;
    email: string | null;
    present_address: string | null;
    permanent_address: string | null;
    admission_date: string;
    qualification: string | null;
    graduation_year: number | null;
    bmdc_number: string | null;
    status: string;
    institute: { name: string } | null;
    admission_branch: { id?: number; name: string } | null;
    medical_college: { name: string } | null;
    enrollments: EnrollmentRecord[];
    other_expenses: ExpenseRecord[];
};

type StudentDetailsApiResponse = { data: StudentDetails };

type StudentEditApiResponse = {
    data: StudentDetails;
    branches: Array<{ id: number; name: string }>;
};

type StudentUpdatePayload = {
    student_code: string;
    full_name: string;
    admission_branch_id: number;
    phone_numbers: string[];
    father_name: string | null;
    mother_name: string | null;
    date_of_birth: string | null;
    gender: string | null;
    email: string | null;
    present_address: string | null;
    permanent_address: string | null;
    admission_date: string | null;
    qualification: string | null;
    graduation_year: number | null;
    bmdc_number: string | null;
};

const avatarColors = [
    'bg-rose-100 text-rose-700',
    'bg-sky-100 text-sky-700',
    'bg-violet-100 text-violet-700',
    'bg-amber-100 text-amber-700',
    'bg-emerald-100 text-emerald-700',
    'bg-cyan-100 text-cyan-700',
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Academy', href: '/academy/students' },
    { title: 'Students', href: '/academy/students' },
];

function toStudent(record: StudentApiRecord): Student {
    const initials = record.full_name.trim().split(/\s+/).slice(0, 2)
        .map((part) => part[0]?.toUpperCase()).join('') || 'ST';
    const enrollment = record.latest_enrollment;

    return {
        id: record.id,
        code: record.student_code,
        name: record.full_name,
        phone: record.phone,
        course: enrollment?.course?.name ?? '—',
        branch: enrollment?.branch?.name ?? record.admission_branch?.name ?? '—',
        batch: enrollment?.batch?.name ?? '—',
        status: record.status,
        admitted: record.admission_date ?? '—',
        initials,
        color: avatarColors[record.id % avatarColors.length],
    };
}

function statusLabel(status: string): string {
    return status.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function paginationItems(currentPage: number, lastPage: number): Array<number | '…'> {
    const visiblePageCount = 5;

    if (lastPage <= visiblePageCount + 1) {
        return Array.from({ length: lastPage }, (_, index) => index + 1);
    }

    const start = Math.min(
        Math.max(1, currentPage - Math.floor(visiblePageCount / 2)),
        lastPage - visiblePageCount + 1,
    );
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

export default function Students() {
    const [students, setStudents] = useState<Student[]>([]);
    const [pagination, setPagination] = useState<StudentPage>({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
        from: null,
        to: null,
    });
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(15);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState('');
    const [search, setSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [appliedSearch, setAppliedSearch] = useState('');
    const [searchSuggestions, setSearchSuggestions] = useState<Student[]>([]);
    const [suggestionsLoading, setSuggestionsLoading] = useState(false);
    const [suggestionsOpen, setSuggestionsOpen] = useState(false);
    const [highlightedSuggestion, setHighlightedSuggestion] = useState(-1);
    const searchContainerRef = useRef<HTMLLabelElement>(null);
    const [filterOptions, setFilterOptions] = useState<StudentFilterOptions>(emptyFilterOptions);
    const [courseFilter, setCourseFilter] = useState('');
    const [batchFilter, setBatchFilter] = useState('');
    const [branchFilter, setBranchFilter] = useState('');
    const [draftAdmissionYear, setDraftAdmissionYear] = useState('');
    const [draftAdmissionMonth, setDraftAdmissionMonth] = useState('');
    const [appliedAdmissionYear, setAppliedAdmissionYear] = useState('');
    const [appliedAdmissionMonth, setAppliedAdmissionMonth] = useState('');
    const [admissionFrom, setAdmissionFrom] = useState('');
    const [admissionTo, setAdmissionTo] = useState('');
    const [sortBy, setSortBy] = useState('created_at');
    const [sortDirection, setSortDirection] = useState('desc');
    const [selectedStudentCode, setSelectedStudentCode] = useState<string | null>(null);
    const [studentDetails, setStudentDetails] = useState<StudentDetails | null>(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const [detailError, setDetailError] = useState('');
    const [editingStudentCode, setEditingStudentCode] = useState<string | null>(null);
    const [editStartedFromDetails, setEditStartedFromDetails] = useState(false);

    // Debounce free-text input to avoid an API request for every keystroke.
    useEffect(() => {
        const timeout = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
        return () => window.clearTimeout(timeout);
    }, [search]);

    // Fetch a small, independent result set for the autocomplete menu. Typing
    // never changes the registry table; Enter or selecting a result applies it.
    useEffect(() => {
        if (debouncedSearch.length < 2) {
            setSearchSuggestions([]);
            setSuggestionsLoading(false);
            return;
        }

        const controller = new AbortController();
        async function loadSearchSuggestions() {
            setSuggestionsLoading(true);
            try {
                const params = new URLSearchParams({ search: debouncedSearch, page: '1', per_page: '7', sort_by: 'full_name', sort_direction: 'asc' });
                const response = await fetch(`/api/v1/students?${params.toString()}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error('Search suggestions are unavailable.');
                const result = await response.json() as StudentApiResponse;
                setSearchSuggestions(result.data.map(toStudent));
            } catch (error) {
                if (error instanceof DOMException && error.name === 'AbortError') return;
                setSearchSuggestions([]);
            } finally {
                if (!controller.signal.aborted) setSuggestionsLoading(false);
            }
        }
        void loadSearchSuggestions();
        return () => controller.abort();
    }, [debouncedSearch]);

    useEffect(() => {
        function closeSuggestionsOnOutsideClick(event: MouseEvent) {
            if (event.target instanceof Node && !searchContainerRef.current?.contains(event.target)) {
                setSuggestionsOpen(false);
                setHighlightedSuggestion(-1);
            }
        }
        document.addEventListener('mousedown', closeSuggestionsOnOutsideClick);
        return () => document.removeEventListener('mousedown', closeSuggestionsOnOutsideClick);
    }, []);

    useEffect(() => {
        const controller = new AbortController();

        async function loadStudents() {
            setLoading(true);
            setLoadError('');

            try {
                const params = new URLSearchParams({
                    page: String(page),
                    per_page: String(perPage),
                    include_filters: '1',
                    sort_by: sortBy,
                    sort_direction: sortDirection,
                });
                if (appliedSearch) params.set('search', appliedSearch);
                if (courseFilter) params.set('course_id', courseFilter);
                if (batchFilter) params.set('batch_id', batchFilter);
                if (branchFilter) params.set('branch_id', branchFilter);
                if (appliedAdmissionYear) params.set('admission_year', appliedAdmissionYear);
                if (appliedAdmissionMonth) params.set('admission_month', appliedAdmissionMonth);
                if (admissionFrom) params.set('date_from', admissionFrom);
                if (admissionTo) params.set('date_to', admissionTo);

                const response = await fetch(`/api/v1/students?${params.toString()}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });

                if (response.redirected) {
                    throw new Error('Your session has expired. Sign in again to view student records.');
                }

                if (!response.ok) {
                    throw new Error(response.status === 401 || response.status === 403
                        ? 'You are not authorized to view student records.'
                        : 'Could not load student records. Please try again.');
                }

                const result = await response.json() as StudentApiResponse;
                setStudents(result.data.map(toStudent));
                setPagination(result.meta);
                setFilterOptions(result.filters ?? emptyFilterOptions);
            } catch (error) {
                if (error instanceof DOMException && error.name === 'AbortError') return;
                setStudents([]);
                setLoadError(error instanceof Error ? error.message : 'Could not load student records.');
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }

        void loadStudents();
        return () => controller.abort();
    }, [page, perPage, appliedSearch, courseFilter, batchFilter, branchFilter, appliedAdmissionYear, appliedAdmissionMonth, admissionFrom, admissionTo, sortBy, sortDirection]);

    useEffect(() => {
        if (!selectedStudentCode) {
            setStudentDetails(null);
            setDetailError('');
            return;
        }

        const studentCode = selectedStudentCode;
        const controller = new AbortController();

        async function loadStudentDetails() {
            setDetailLoading(true);
            setDetailError('');
            setStudentDetails(null);

            try {
                const response = await fetch(`/api/v1/students/${encodeURIComponent(studentCode)}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error(response.status === 404
                        ? 'Student record was not found.'
                        : 'Could not load student details. Please try again.');
                }

                const result = await response.json() as StudentDetailsApiResponse;
                setStudentDetails(result.data);
            } catch (error) {
                if (error instanceof DOMException && error.name === 'AbortError') return;
                setDetailError(error instanceof Error ? error.message : 'Could not load student details.');
            } finally {
                if (!controller.signal.aborted) setDetailLoading(false);
            }
        }

        void loadStudentDetails();
        return () => controller.abort();
    }, [selectedStudentCode]);

    // The API applies filters before pagination; render the current server page as-is.
    const filteredStudents = students;
    const batchesForCourse = filterOptions.batches.filter((batch) => !courseFilter || batch.course_id === Number(courseFilter));

    function changeCourseFilter(courseId: string) {
        setCourseFilter(courseId);
        if (batchFilter && !filterOptions.batches.some((batch) => String(batch.id) === batchFilter && (!courseId || batch.course_id === Number(courseId)))) {
            setBatchFilter('');
        }
        setPage(1);
    }

    function clearFilters() {
        setSearch('');
        setDebouncedSearch('');
        setAppliedSearch('');
        setSearchSuggestions([]);
        setSuggestionsOpen(false);
        setCourseFilter('');
        setBatchFilter('');
        setBranchFilter('');
        setDraftAdmissionYear('');
        setDraftAdmissionMonth('');
        setAppliedAdmissionYear('');
        setAppliedAdmissionMonth('');
        setAdmissionFrom('');
        setAdmissionTo('');
        setSortBy('created_at');
        setSortDirection('desc');
        setPage(1);
    }

    function applySearch(value: string) {
        setSearch(value);
        setDebouncedSearch(value.trim());
        setAppliedSearch(value.trim());
        setPage(1);
        setSuggestionsOpen(false);
        setHighlightedSuggestion(-1);
    }

    function applyFilters() {
        setAppliedAdmissionYear(draftAdmissionYear);
        setAppliedAdmissionMonth(draftAdmissionMonth);
        setPage(1);
    }

    const admissionYears = Array.from({ length: 30 }, (_, index) => String(new Date().getFullYear() - index));
    const admissionMonths = [
        ['1', 'January'], ['2', 'February'], ['3', 'March'], ['4', 'April'],
        ['5', 'May'], ['6', 'June'], ['7', 'July'], ['8', 'August'],
        ['9', 'September'], ['10', 'October'], ['11', 'November'], ['12', 'December'],
    ];

    if (loading && !loadError) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Students | CMUD Academy" />
                <StudentsPagePreloader />
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Students | CMUD Academy" />
            <main className="min-h-screen bg-[#F8F9FA] px-4 py-6 text-[#2C3E50] sm:px-7 lg:px-10">
                <div className="mx-auto max-w-7xl space-y-6">
                    <header className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                        <div>
                            <div className="mb-3 flex items-center gap-2 text-xs font-semibold text-[#008295]">
                                <span className="grid h-7 w-7 place-items-center rounded-lg bg-[#E5F3F4]"><GraduationCap size={16} /></span>
                                STUDENTS &amp; ACADEMY <span className="text-slate-300">/</span> REGISTRY
                            </div>
                            <h1 className="text-3xl font-semibold tracking-tight text-[#1B365D]">Student records</h1>
                            <p className="mt-2 text-sm text-slate-500">Search, review, and manage student admissions across courses and branches.</p>
                        </div>
                        <Link href="/academy/students/admission" className="inline-flex h-11 items-center gap-2 self-start rounded-xl bg-[#1B365D] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#254878] sm:self-auto">
                            <UserPlus size={16} /> New admission
                        </Link>
                    </header>

                    <div className="flex items-start gap-3 rounded-2xl border border-[#BFE4E8] bg-[#EFF9FA] px-4 py-3 text-sm text-[#365C65]">
                        <CircleHelp className="mt-0.5 shrink-0 text-[#008295]" size={17} />
                        <p><span className="font-semibold">Live student records.</span> Search and filters run against the complete Academy student registry before pagination.</p>
                    </div>

                    {loadError && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{loadError}</div>}

                    <section className="overflow-hidden rounded-[22px] border border-slate-200/80 bg-white shadow-sm">
                        <div className="border-b border-slate-100 p-5 sm:p-6">
                            <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                                <div>
                                    <h2 className="text-lg font-semibold text-[#1B365D]">All students</h2>
                                    <p className="mt-1 text-xs text-slate-500">Search all records by student name, registration ID, or phone.</p>
                                </div>
                                <div className="flex flex-col gap-2 sm:flex-row">
                                    <label ref={searchContainerRef} className="relative block sm:w-[320px]">
                                        <Search className="absolute top-1/2 left-3 -translate-y-1/2 text-slate-400" size={17} />
                                        <input value={search} role="combobox" aria-autocomplete="list" aria-expanded={suggestionsOpen && search.trim().length >= 2} aria-controls="student-search-suggestions" aria-activedescendant={highlightedSuggestion >= 0 ? `student-search-option-${highlightedSuggestion}` : undefined} onFocus={() => setSuggestionsOpen(true)} onChange={(event) => { setSearch(event.target.value); setSuggestionsOpen(true); setHighlightedSuggestion(-1); }} onKeyDown={(event) => {
                                            if (event.key === 'ArrowDown' && suggestionsOpen && searchSuggestions.length) { event.preventDefault(); setHighlightedSuggestion((current) => (current + 1) % searchSuggestions.length); }
                                            else if (event.key === 'ArrowUp' && suggestionsOpen && searchSuggestions.length) { event.preventDefault(); setHighlightedSuggestion((current) => current <= 0 ? searchSuggestions.length - 1 : current - 1); }
                                            else if (event.key === 'Enter') { event.preventDefault(); applySearch(highlightedSuggestion >= 0 ? searchSuggestions[highlightedSuggestion].code : search); }
                                            else if (event.key === 'Escape') { setSuggestionsOpen(false); setHighlightedSuggestion(-1); }
                                        }} placeholder="Search all students..." className="h-10 w-full rounded-xl border border-slate-200 bg-white pr-10 pl-10 text-sm outline-none transition placeholder:text-slate-400 focus:border-[#008295] focus:ring-3 focus:ring-[#008295]/10" />
                                        {search && <button type="button" onClick={() => applySearch('')} className="absolute top-1/2 right-3 -translate-y-1/2 text-slate-400 hover:text-slate-700" aria-label="Clear search"><X size={15} /></button>}
                                        {suggestionsOpen && search.trim().length >= 2 && <div id="student-search-suggestions" role="listbox" className="absolute top-full right-0 left-0 z-30 mt-2 max-h-80 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                                            {suggestionsLoading ? <p role="status" className="px-4 py-3 text-xs text-slate-500">Searching students...</p> : searchSuggestions.length ? searchSuggestions.map((student, index) => <button id={`student-search-option-${index}`} key={student.id} type="button" role="option" aria-selected={highlightedSuggestion === index} onMouseEnter={() => setHighlightedSuggestion(index)} onMouseDown={(event) => event.preventDefault()} onClick={() => applySearch(student.code)} className={`flex w-full items-center gap-3 px-4 py-3 text-left transition ${highlightedSuggestion === index ? 'bg-[#EFF9FA]' : 'hover:bg-slate-50'}`}>
                                                <span className={`grid h-9 w-9 shrink-0 place-items-center rounded-full text-[11px] font-bold ${student.color}`}>{student.initials}</span><span className="min-w-0 flex-1"><span className="block truncate text-sm font-semibold text-[#1B365D]">{student.name}</span><span className="mt-0.5 block truncate font-mono text-[11px] text-slate-500">{student.code} <span className="font-sans">· {student.phone}</span></span></span><span className="hidden max-w-28 truncate text-right text-[11px] text-slate-400 sm:block">{student.course}</span>
                                            </button>) : <p className="px-4 py-3 text-xs text-slate-500">No matching students. Press Enter to search the registry.</p>}
                                        </div>}
                                    </label>
                                    <label className="relative">
                                        <SlidersHorizontal className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-slate-400" size={15} />
                                        <select aria-label="Filter students by course" value={courseFilter} onChange={(event) => changeCourseFilter(event.target.value)} className="h-10 w-full appearance-none rounded-xl border border-slate-200 bg-white pr-9 pl-9 text-sm text-slate-600 outline-none focus:border-[#008295] sm:w-[190px]">
                                            <option value="">All courses</option>
                                            {filterOptions.courses.map((course) => <option key={course.id} value={course.id}>{course.name}</option>)}
                                        </select>
                                        <ChevronDown className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-slate-400" size={15} />
                                    </label>
                                </div>
                            </div>
                            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <FilterSelect label="Batch" value={batchFilter} onChange={(value) => { setBatchFilter(value); setPage(1); }}>
                                    <option value="">All batches</option>{batchesForCourse.map((batch) => <option key={batch.id} value={batch.id}>{batch.name}</option>)}
                                </FilterSelect>
                                <FilterSelect label="Branch" value={branchFilter} onChange={(value) => { setBranchFilter(value); setPage(1); }}>
                                    <option value="">All branches</option>{filterOptions.branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}
                                </FilterSelect>
                                <FilterSelect label="Admission year" value={draftAdmissionYear} onChange={(value) => { setDraftAdmissionYear(value); setDraftAdmissionMonth(''); }}>
                                    <option value="">Any year</option>{admissionYears.map((year) => <option key={year} value={year}>{year}</option>)}
                                </FilterSelect>
                                <FilterSelect label="Admission month" value={draftAdmissionMonth} onChange={setDraftAdmissionMonth}>
                                    <option value="">Any month</option>{admissionMonths.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                </FilterSelect>
                                <label className="block text-xs font-medium text-slate-500">Admitted from<input type="date" value={admissionFrom} onChange={(event) => setAdmissionFrom(event.target.value)} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-[#008295]" /></label>
                                <label className="block text-xs font-medium text-slate-500">Admitted to<input type="date" value={admissionTo} min={admissionFrom || undefined} onChange={(event) => setAdmissionTo(event.target.value)} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-[#008295]" /></label>
                                <FilterSelect label="Sort by" value={sortBy} onChange={(value) => { setSortBy(value); setPage(1); }}>
                                    <option value="created_at">Recently added</option><option value="student_code">Student ID</option><option value="full_name">Name</option><option value="admission_date">Admission date</option>
                                </FilterSelect>
                                <FilterSelect label="Direction" value={sortDirection} onChange={(value) => { setSortDirection(value); setPage(1); }}>
                                    <option value="desc">Descending</option><option value="asc">Ascending</option>
                                </FilterSelect>
                            </div>
                            <div className="mt-3 flex justify-end">
                                <div className="flex gap-2">
                                    <button type="button" onClick={clearFilters} className="rounded-lg px-3 py-2 text-xs font-semibold text-[#008295] transition hover:bg-[#EFF9FA]">Clear all filters</button>
                                    <button type="button" onClick={applyFilters} disabled={loading} className="rounded-lg bg-[#1B365D] px-4 py-2 text-xs font-semibold text-white transition hover:bg-[#254878] disabled:opacity-50">Filter students</button>
                                </div>
                            </div>
                        </div>

                        <div className="overflow-x-auto" aria-busy={loading}>
                            <table className="w-full min-w-[850px] text-left">
                                <thead className="bg-[#FAFBFC] text-[10px] font-bold tracking-[0.11em] text-slate-400 uppercase">
                                    <tr><th className="px-6 py-3">Student</th><th className="px-5 py-3">Student ID</th><th className="px-5 py-3">Course / batch</th><th className="px-5 py-3">Branch</th><th className="px-5 py-3">Status</th><th className="px-5 py-3">Admitted</th><th className="px-5 py-3"><span className="sr-only">Actions</span></th></tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {filteredStudents.map((student) => <tr key={student.id} className="group transition hover:bg-[#F8FBFC]">
                                        <td className="px-6 py-4"><div className="flex items-center gap-3"><span className={`grid h-10 w-10 shrink-0 place-items-center rounded-full text-xs font-bold ${student.color}`}>{student.initials}</span><div><p className="text-sm font-semibold text-[#2C3E50]">{student.name}</p><p className="mt-0.5 text-xs text-slate-400">{student.phone}</p></div></div></td>
                                        <td className="px-5 py-4"><span className="font-mono text-xs font-medium text-[#1B365D]">{student.code}</span></td>
                                        <td className="px-5 py-4"><p className="text-sm font-medium text-slate-700">{student.course}</p><p className="mt-0.5 text-xs text-slate-400">{student.batch}</p></td>
                                        <td className="px-5 py-4 text-sm text-slate-600">{student.branch}</td>
                                        <td className="px-5 py-4"><StatusPill status={statusLabel(student.status)} /></td>
                                        <td className="px-5 py-4 text-xs text-slate-500">{student.admitted}</td>
                                        <td className="px-5 py-4"><div className="flex items-center gap-1">
                                            <button type="button" onClick={() => setSelectedStudentCode(student.code)} className="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-[#008295] opacity-80 transition hover:bg-[#EAF6F7] hover:opacity-100">View <span aria-hidden="true">↗</span></button>
                                            <button type="button" onClick={() => { setEditStartedFromDetails(false); setEditingStudentCode(student.code); }} className="inline-flex items-center rounded-lg px-2.5 py-1.5 text-xs font-semibold text-[#1B365D] transition hover:bg-[#EDF1F7]">Update</button>
                                        </div></td>
                                    </tr>)}
                                </tbody>
                            </table>
                            {!loading && filteredStudents.length === 0 && <div className="px-6 py-16 text-center"><span className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-400"><Search size={20} /></span><h3 className="mt-4 text-sm font-semibold text-[#1B365D]">{pagination.total ? 'No students match these filters' : 'No student records found'}</h3><p className="mt-1 text-xs text-slate-500">{pagination.total ? 'Adjust or clear the selected filters and try again.' : 'Student records will appear here when available.'}</p></div>}
                            {loading && students.length > 0 && <div role="status" className="flex items-center justify-center gap-2 px-6 py-4 text-xs font-medium text-[#008295]"><span className="h-4 w-4 animate-spin rounded-full border-2 border-[#BFE4E8] border-t-[#008295]" /> Loading page…</div>}
                        </div>
                        <div className="flex flex-col justify-between gap-3 border-t border-slate-100 px-5 py-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:px-6">
                            <span>Showing <strong className="text-slate-700">{pagination.from ?? 0}–{pagination.to ?? 0}</strong> of <strong className="text-slate-700">{pagination.total}</strong> student records</span>
                            <div className="flex flex-wrap items-center gap-2">
                                <label className="flex items-center gap-2">Per page
                                    <select value={perPage} onChange={(event) => { setPerPage(Number(event.target.value)); setPage(1); }} className="h-8 rounded-lg border border-slate-200 bg-white px-2 text-xs text-slate-600">
                                        {[15, 30, 50, 100].map((size) => <option key={size} value={size}>{size}</option>)}
                                    </select>
                                </label>
                                <div className="flex items-center gap-1" aria-label="Student record pages">
                                    <button type="button" disabled={loading || pagination.current_page <= 1} onClick={() => setPage((current) => Math.max(1, current - 1))} aria-label="Previous page" className="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 text-slate-500 disabled:text-slate-300"><ChevronLeft size={16} /></button>
                                    {paginationItems(pagination.current_page, pagination.last_page).map((item, index) => item === '…'
                                        ? <span key={`ellipsis-${index}`} className="px-1 text-slate-400">…</span>
                                        : <button key={item} type="button" disabled={loading} aria-current={item === pagination.current_page ? 'page' : undefined} onClick={() => setPage(item)} className={`grid h-8 min-w-8 place-items-center rounded-lg px-2 ${item === pagination.current_page ? 'bg-[#1B365D] font-semibold text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50'}`}>{item}</button>)}
                                    <button type="button" disabled={loading || pagination.current_page >= pagination.last_page} onClick={() => setPage((current) => Math.min(pagination.last_page, current + 1))} aria-label="Next page" className="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 text-slate-500 disabled:text-slate-300"><ChevronRight size={16} /></button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div className="px-1 pb-4 text-xs text-slate-400"><span>CMUD ERP · Students &amp; Academy</span></div>
                </div>
            </main>
            {selectedStudentCode && <StudentDetailModal student={studentDetails} loading={detailLoading} error={detailError} onEdit={() => { setEditStartedFromDetails(true); setEditingStudentCode(selectedStudentCode); }} onClose={() => setSelectedStudentCode(null)} />}
            {editingStudentCode && <StudentEditModal
                studentCode={editingStudentCode}
                onClose={() => setEditingStudentCode(null)}
                onUpdated={(updated) => {
                    if (editStartedFromDetails) {
                        setStudentDetails(updated);
                        setSelectedStudentCode(updated.student_code);
                    }
                    setStudents((current) => current.map((student) => student.code === updated.student_code || student.code === editingStudentCode
                        ? { ...student, code: updated.student_code, name: updated.full_name, phone: updated.phone_numbers?.[0] ?? updated.phone, branch: updated.admission_branch?.name ?? student.branch }
                        : student));
                    setEditingStudentCode(null);
                    setEditStartedFromDetails(false);
                }}
            />}
        </AppLayout>
    );
}

function StudentsPagePreloader() {
    return (
        <main role="status" aria-live="polite" aria-label="Loading student records" className="min-h-screen bg-[#F8F9FA] px-4 py-6 sm:px-7 lg:px-10">
            <span className="sr-only">Loading student records…</span>
            <div className="mx-auto max-w-7xl animate-pulse space-y-6">
                <header className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                    <div className="space-y-3">
                        <div className="h-7 w-40 rounded-lg bg-slate-200" />
                        <div className="h-9 w-64 rounded-lg bg-slate-200" />
                        <div className="h-4 w-80 max-w-full rounded bg-slate-200" />
                    </div>
                </header>

                <div className="h-14 rounded-2xl border border-slate-200 bg-white" />

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-hidden="true">
                    {Array.from({ length: 4 }, (_, index) => <div key={index} className="h-32 rounded-[18px] border border-slate-200 bg-white p-5"><div className="h-10 w-10 rounded-xl bg-slate-200" /><div className="mt-5 h-5 w-24 rounded bg-slate-200" /><div className="mt-2 h-3 w-32 rounded bg-slate-100" /></div>)}
                </section>

                <section className="overflow-hidden rounded-[22px] border border-slate-200 bg-white" aria-hidden="true">
                    <div className="flex flex-col justify-between gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:p-6">
                        <div className="space-y-2"><div className="h-5 w-32 rounded bg-slate-200" /><div className="h-3 w-60 rounded bg-slate-100" /></div>
                        <div className="flex gap-2"><div className="h-10 w-64 rounded-xl bg-slate-100" /><div className="h-10 w-40 rounded-xl bg-slate-100" /></div>
                    </div>
                    <div className="space-y-0 divide-y divide-slate-100 px-5">
                        {Array.from({ length: 7 }, (_, index) => <div key={index} className="grid h-[72px] grid-cols-[1.5fr_1fr_1fr_0.8fr_0.7fr] items-center gap-5">
                            <div className="flex items-center gap-3"><div className="h-10 w-10 shrink-0 rounded-full bg-slate-200" /><div className="space-y-2"><div className="h-3 w-32 rounded bg-slate-200" /><div className="h-2.5 w-24 rounded bg-slate-100" /></div></div>
                            <div className="h-3 w-24 rounded bg-slate-100" /><div className="h-3 w-28 rounded bg-slate-100" /><div className="h-3 w-20 rounded bg-slate-100" /><div className="h-6 w-16 rounded-full bg-slate-100" />
                        </div>)}
                    </div>
                    <div className="flex justify-between border-t border-slate-100 px-6 py-4"><div className="h-3 w-40 rounded bg-slate-100" /><div className="h-8 w-36 rounded-lg bg-slate-100" /></div>
                </section>
            </div>
        </main>
    );
}

/** Keep filter labels and select styling consistent across the student registry. */
function FilterSelect({ label, value, onChange, children }: { label: string; value: string; onChange: (value: string) => void; children: ReactNode }) {
    return (
        <label className="block text-xs font-medium text-slate-500">
            {label}
            <select value={value} onChange={(event) => onChange(event.target.value)} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-[#008295]">
                {children}
            </select>
        </label>
    );
}

function StatusPill({ status }: { status: string }) {
    const style = status.toLowerCase() === 'active'
        ? 'bg-emerald-50 text-emerald-700'
        : status.toLowerCase() === 'completed'
            ? 'bg-slate-100 text-slate-600'
            : 'bg-sky-50 text-sky-700';
    return <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ${style}`}><span className="h-1.5 w-1.5 rounded-full bg-current" />{status}</span>;
}

function StudentDetailModal({ student, loading, error, onClose, onEdit }: { student: StudentDetails | null; loading: boolean; error: string; onClose: () => void; onEdit: () => void }) {
    return <div className="fixed inset-0 z-40 flex justify-end bg-[#10233B]/45 backdrop-blur-[1px]" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
        <aside role="dialog" aria-modal="true" aria-labelledby="student-detail-title" className="h-full w-full max-w-3xl overflow-y-auto bg-[#F8F9FA] shadow-2xl">
            <div className="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white px-6 py-5">
                <div><p className="text-[10px] font-bold tracking-[0.15em] text-[#008295] uppercase">Student record</p><h2 id="student-detail-title" className="mt-1 text-lg font-semibold text-[#1B365D]">{student?.full_name ?? 'Student details'}</h2></div>
                <div className="flex items-center gap-2">
                    {student && <button type="button" onClick={onEdit} className="rounded-lg bg-[#1B365D] px-3 py-2 text-xs font-semibold text-white hover:bg-[#254878]">Edit student</button>}
                    <button type="button" onClick={onClose} aria-label="Close student details" className="rounded-lg p-2 text-slate-400 hover:bg-slate-100"><X size={18} /></button>
                </div>
            </div>

            {loading && <div role="status" className="space-y-5 p-6"><p className="text-sm text-slate-500">Loading student details…</p><div className="animate-pulse space-y-4"><div className="h-28 rounded-2xl bg-white" /><div className="h-48 rounded-2xl bg-white" /><div className="h-56 rounded-2xl bg-white" /></div></div>}
            {!loading && error && <div role="alert" className="m-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div>}

            {!loading && student && <div className="space-y-5 p-5 sm:p-6">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div><p className="font-mono text-xs text-slate-400">{student.student_code}</p><h3 className="mt-1 text-xl font-semibold text-[#1B365D]">{student.full_name}</h3><p className="mt-1 text-sm text-slate-500">{student.institute?.name ?? 'Institute not recorded'}</p></div>
                        <StatusPill status={statusLabel(student.status)} />
                    </div>
                    <div className="mt-5 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <DetailField label="Father's name" value={student.father_name} />
                        <DetailField label="Mother's name" value={student.mother_name} />
                        <DetailField label="Date of birth" value={student.date_of_birth} />
                        <DetailField label="Gender" value={student.gender} />
                        <DetailField label="Phone numbers" value={student.phone_numbers?.join(', ') || [student.phone, student.alternative_phone].filter(Boolean).join(', ')} />
                        <DetailField label="Email" value={student.email} />
                        <DetailField label="Admission date" value={student.admission_date} />
                        <DetailField label="Admission branch" value={student.admission_branch?.name} />
                        <DetailField label="Medical college" value={student.medical_college?.name} />
                        <DetailField label="Qualification" value={student.qualification} />
                        <DetailField label="Graduation year" value={student.graduation_year?.toString()} />
                        <DetailField label="BMDC number" value={student.bmdc_number} />
                        <DetailField label="Present address" value={student.present_address} />
                        <DetailField label="Permanent address" value={student.permanent_address} />
                    </div>
                </section>

                <section className="space-y-3">
                    <div><h3 className="text-base font-semibold text-[#1B365D]">Enrollments &amp; tuition</h3><p className="mt-1 text-xs text-slate-500">Course enrollment and tuition payment history.</p></div>
                    {student.enrollments.length === 0 && <EmptyDetail>No enrollments recorded.</EmptyDetail>}
                    {student.enrollments.map((enrollment) => <article key={enrollment.enrollment_code} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex flex-wrap justify-between gap-3"><div><p className="font-mono text-xs text-slate-400">{enrollment.enrollment_code}</p><h4 className="mt-1 font-semibold text-[#1B365D]">{enrollment.course?.name ?? 'Course not recorded'}</h4><p className="mt-1 text-xs text-slate-500">{[enrollment.branch?.name, enrollment.batch?.name].filter(Boolean).join(' · ') || 'Branch or batch not recorded'}</p></div><StatusPill status={statusLabel(enrollment.status)} /></div>
                        <div className="mt-4 grid gap-3 text-xs sm:grid-cols-3"><DetailField label="Enrolled" value={enrollment.enrollment_date} /><DetailField label="Starts" value={enrollment.starts_on} /><DetailField label="Expected completion" value={enrollment.expected_completion_on} /></div>
                        {enrollment.tuition && <div className="mt-5 border-t border-slate-100 pt-4"><div className="flex flex-wrap justify-between gap-2"><div><p className="text-xs font-semibold text-slate-700">Tuition {enrollment.tuition.tuition_number}</p><p className="mt-1 text-xs text-slate-500">Issued {enrollment.tuition.issued_on}{enrollment.tuition.due_on ? ` · Due ${enrollment.tuition.due_on}` : ''}</p></div><StatusPill status={statusLabel(enrollment.tuition.status)} /></div><div className="mt-3 flex flex-wrap gap-4 text-xs text-slate-600"><span>Amount: {formatMoney(enrollment.tuition.original_amount)}</span><span>Waiver: {formatMoney(enrollment.tuition.waiver_amount)}</span></div><PaymentHistory payments={enrollment.tuition.payments} /></div>}
                    </article>)}
                </section>

                <section className="space-y-3">
                    <div><h3 className="text-base font-semibold text-[#1B365D]">Other expenses</h3><p className="mt-1 text-xs text-slate-500">Expense charges and payment history.</p></div>
                    {student.other_expenses.length === 0 && <EmptyDetail>No other expenses recorded.</EmptyDetail>}
                    {student.other_expenses.map((expense) => <article key={expense.expense_number} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex flex-wrap justify-between gap-3"><div><p className="font-mono text-xs text-slate-400">{expense.expense_number}</p><h4 className="mt-1 font-semibold text-[#1B365D]">{expense.expense_type ?? expense.description}</h4><p className="mt-1 text-xs text-slate-500">{expense.branch ?? 'Branch not recorded'} · Issued {expense.issued_on}{expense.due_on ? ` · Due ${expense.due_on}` : ''}</p></div><StatusPill status={statusLabel(expense.status)} /></div>
                        <p className="mt-3 text-sm text-slate-600">{expense.description}</p><div className="mt-3 flex flex-wrap gap-4 text-xs text-slate-600"><span>Quantity: {expense.quantity}</span><span>Unit amount: {formatMoney(expense.unit_amount)}</span><span>Waiver: {formatMoney(expense.waiver_amount)}</span></div><PaymentHistory payments={expense.payments} />
                    </article>)}
                </section>
            </div>}
        </aside>
    </div>;
}

function StudentEditModal({ studentCode, onClose, onUpdated }: { studentCode: string; onClose: () => void; onUpdated: (student: StudentDetails) => void }) {
    const [student, setStudent] = useState<StudentDetails | null>(null);
    const [branches, setBranches] = useState<Array<{ id: number; name: string }>>([]);
    const [form, setForm] = useState<StudentUpdatePayload | null>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        const controller = new AbortController();
        async function loadEditData() {
            setLoading(true);
            setError('');
            try {
                const response = await fetch(`/api/v1/student/${encodeURIComponent(studentCode)}/edit`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                const result = await response.json() as StudentEditApiResponse;
                if (!response.ok) throw new Error(response.status === 404 ? 'Student record was not found.' : 'Could not load student information for editing.');
                setStudent(result.data);
                setBranches(result.branches);
                setForm({
                    student_code: result.data.student_code,
                    full_name: result.data.full_name,
                    admission_branch_id: result.data.admission_branch?.id ?? result.branches[0]?.id ?? 0,
                    phone_numbers: result.data.phone_numbers?.length ? result.data.phone_numbers : [result.data.phone, result.data.alternative_phone].filter((phone): phone is string => Boolean(phone)),
                    father_name: result.data.father_name,
                    mother_name: result.data.mother_name,
                    date_of_birth: result.data.date_of_birth,
                    gender: result.data.gender,
                    email: result.data.email,
                    present_address: result.data.present_address,
                    permanent_address: result.data.permanent_address,
                    admission_date: result.data.admission_date,
                    qualification: result.data.qualification,
                    graduation_year: result.data.graduation_year,
                    bmdc_number: result.data.bmdc_number,
                });
            } catch (loadError) {
                if (loadError instanceof DOMException && loadError.name === 'AbortError') return;
                setError(loadError instanceof Error ? loadError.message : 'Could not load student information.');
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }
        void loadEditData();
        return () => controller.abort();
    }, [studentCode]);

    function setField<K extends keyof StudentUpdatePayload>(key: K, value: StudentUpdatePayload[K]) {
        setForm((current) => current ? { ...current, [key]: value } : current);
    }

    async function saveStudent(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!form) return;
        setSaving(true);
        setError('');
        try {
            const response = await fetch(`/api/v1/student/${encodeURIComponent(studentCode)}/edit`, {
                method: 'PUT',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(form),
            });
            const result = await response.json() as StudentDetailsApiResponse & { message?: string; errors?: Record<string, string[]> };
            if (!response.ok) {
                const validationError = result.errors ? Object.values(result.errors).flat()[0] : null;
                throw new Error(validationError ?? result.message ?? 'Could not update student information.');
            }
            onUpdated(result.data);
        } catch (saveError) {
            setError(saveError instanceof Error ? saveError.message : 'Could not update student information.');
        } finally {
            setSaving(false);
        }
    }

    return <div className="fixed inset-0 z-50 flex justify-center overflow-y-auto bg-[#10233B]/55 p-4 backdrop-blur-[1px]" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
        <section role="dialog" aria-modal="true" aria-labelledby="student-edit-title" className="my-auto w-full max-w-3xl rounded-2xl bg-[#F8F9FA] shadow-2xl">
            <header className="flex items-center justify-between border-b border-slate-100 bg-white px-6 py-5">
                <div><p className="text-[10px] font-bold tracking-[0.15em] text-[#008295] uppercase">Student record</p><h2 id="student-edit-title" className="mt-1 text-lg font-semibold text-[#1B365D]">Edit student information</h2></div>
                <button type="button" onClick={onClose} aria-label="Close edit student" className="rounded-lg p-2 text-slate-400 hover:bg-slate-100"><X size={18} /></button>
            </header>
            {loading && <div role="status" className="p-6 text-sm text-slate-500">Loading student information…</div>}
            {!loading && error && !form && <div role="alert" className="m-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div>}
            {!loading && form && <form onSubmit={saveStudent} className="space-y-5 p-6">
                {error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</div>}
                <div className="grid gap-4 sm:grid-cols-2">
                    <StudentEditInput label="Student ID" required value={form.student_code} onChange={(value) => setField('student_code', value)} />
                    <StudentEditInput label="Full name" required value={form.full_name} onChange={(value) => setField('full_name', value)} />
                    <label className="block text-xs font-medium text-slate-600">Admission branch <span className="text-rose-500">*</span>
                        <select required value={form.admission_branch_id || ''} onChange={(event) => setField('admission_branch_id', Number(event.target.value))} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-[#008295]">
                            <option value="" disabled>Select a branch</option>{branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}
                        </select>
                    </label>
                    <div className="sm:col-span-2">
                        <p className="text-xs font-medium text-slate-600">Phone numbers <span className="text-rose-500">*</span></p>
                        <div className="mt-1.5 space-y-2">
                            {form.phone_numbers.map((phone, index) => <div key={index} className="flex gap-2">
                                <input required type="tel" value={phone} onChange={(event) => setField('phone_numbers', form.phone_numbers.map((item, itemIndex) => itemIndex === index ? event.target.value : item))} className="h-10 min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-[#008295]" aria-label={`Phone number ${index + 1}`} />
                                {form.phone_numbers.length > 1 && <button type="button" onClick={() => setField('phone_numbers', form.phone_numbers.filter((_, itemIndex) => itemIndex !== index))} className="rounded-lg px-3 text-xs font-semibold text-rose-600 hover:bg-rose-50">Remove</button>}
                            </div>)}
                        </div>
                        <button type="button" onClick={() => setField('phone_numbers', [...form.phone_numbers, ''])} className="mt-2 text-xs font-semibold text-[#008295] hover:underline">+ Add phone number</button>
                    </div>
                    <StudentEditInput label="Father's name" value={form.father_name ?? ''} onChange={(value) => setField('father_name', value || null)} />
                    <StudentEditInput label="Mother's name" value={form.mother_name ?? ''} onChange={(value) => setField('mother_name', value || null)} />
                    <StudentEditInput label="Date of birth" type="date" value={form.date_of_birth ?? ''} onChange={(value) => setField('date_of_birth', value || null)} />
                    <StudentEditInput label="Gender" value={form.gender ?? ''} onChange={(value) => setField('gender', value || null)} />
                    <StudentEditInput label="Email" type="email" value={form.email ?? ''} onChange={(value) => setField('email', value || null)} />
                    <StudentEditInput label="Admission date" type="date" value={form.admission_date ?? ''} onChange={(value) => setField('admission_date', value || null)} />
                    <StudentEditInput label="Qualification" value={form.qualification ?? ''} onChange={(value) => setField('qualification', value || null)} />
                    <StudentEditInput label="Graduation year" type="number" value={form.graduation_year?.toString() ?? ''} onChange={(value) => setField('graduation_year', value ? Number(value) : null)} />
                    <StudentEditInput label="BMDC number" value={form.bmdc_number ?? ''} onChange={(value) => setField('bmdc_number', value || null)} />
                    <label className="block text-xs font-medium text-slate-600 sm:col-span-2">Present address<textarea rows={2} value={form.present_address ?? ''} onChange={(event) => setField('present_address', event.target.value || null)} className="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-[#008295]" /></label>
                    <label className="block text-xs font-medium text-slate-600 sm:col-span-2">Permanent address<textarea rows={2} value={form.permanent_address ?? ''} onChange={(event) => setField('permanent_address', event.target.value || null)} className="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-[#008295]" /></label>
                </div>
                <footer className="flex justify-end gap-2 border-t border-slate-200 pt-4">
                    <button type="button" onClick={onClose} className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" disabled={saving || form.phone_numbers.some((phone) => !phone.trim()) || !form.admission_branch_id} className="rounded-lg bg-[#1B365D] px-4 py-2 text-sm font-semibold text-white hover:bg-[#254878] disabled:cursor-not-allowed disabled:opacity-60">{saving ? 'Saving…' : 'Save changes'}</button>
                </footer>
            </form>}
            {student && <span className="sr-only">Editing {student.student_code}</span>}
        </section>
    </div>;
}

function StudentEditInput({ label, value, onChange, required = false, type = 'text' }: { label: string; value: string; onChange: (value: string) => void; required?: boolean; type?: string }) {
    return <label className="block text-xs font-medium text-slate-600">{label}{required && <span className="text-rose-500"> *</span>}<input required={required} type={type} value={value} onChange={(event) => onChange(event.target.value)} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-[#008295]" /></label>;
}

function DetailField({ label, value }: { label: string; value: string | null | undefined }) {
    return <div><p className="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{label}</p><p className="mt-1 break-words text-sm text-slate-700">{value || '—'}</p></div>;
}

function PaymentHistory({ payments }: { payments: PaymentRecord[] }) {
    return <div className="mt-4 border-t border-slate-100 pt-3">
        <h5 className="text-xs font-semibold text-slate-700">Payments</h5>
        {payments.length === 0 ? <p className="mt-2 text-xs text-slate-400">No payments recorded.</p> : <div className="mt-2 divide-y divide-slate-100">
            {payments.map((payment) => <div key={payment.payment_number} className="flex flex-wrap items-center justify-between gap-2 py-2 text-xs">
                <div><p className="font-medium text-slate-700">{payment.payment_number}</p><p className="mt-0.5 text-slate-400">{payment.paid_at ?? 'Date not recorded'}{payment.payment_method ? ` · ${payment.payment_method}` : ''}{payment.transaction_reference ? ` · Ref ${payment.transaction_reference}` : ''}</p></div>
                <div className="flex items-center gap-2"><span className="font-semibold text-slate-700">{formatMoney(payment.amount)}</span><StatusPill status={statusLabel(payment.status)} /></div>
            </div>)}
        </div>}
    </div>;
}

function EmptyDetail({ children }: { children: string }) {
    return <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-8 text-center text-sm text-slate-400">{children}</div>;
}

function formatMoney(amount: string): string {
    return new Intl.NumberFormat('en-BD', { style: 'currency', currency: 'BDT' }).format(Number(amount));
}
