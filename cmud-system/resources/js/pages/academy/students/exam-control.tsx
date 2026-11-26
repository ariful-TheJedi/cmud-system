import AppLayout from '@/layouts/app-layout';
import { ApiRequestError, useApi } from '@/hooks/use-api';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    AlertCircle,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    FileCheck2,
    LoaderCircle,
    Search,
    Users,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useMemo,
    useState,
    type FormEvent,
} from 'react';

type Batch = {
    id: number;
    course_id: number;
    code: string;
    name: string;
    starts_on: string | null;
    expected_ends_on: string | null;
    status: string;
    course: { id: number; name: string; duration_months: number };
    branch: { id: number; name: string };
};
type Course = { id: number; name: string; duration_months: number };
type Exam = {
    id: number;
    exam_name: string;
    attempt_number: number;
    attempt_status: 'scheduled' | 'appeared' | 'absent' | 'cancelled';
    exam_date: string;
    total_marks: string | null;
    obtained_marks: string | null;
    grade: string | null;
    result_status: 'pending' | 'passed' | 'failed' | 'incomplete';
    remarks: string | null;
};
type Stage = {
    stage_record_id: number;
    stage: { id: number; code: string; name: string; requires_exam: boolean };
    stage_status: string;
    exam_eligibility: { eligible: boolean; reasons: string[] };
    exam_status: string;
    can_schedule: boolean;
    latest_exam: Exam | null;
    attempts: Exam[];
};
type RosterStudent = {
    enrollment_id: number;
    enrollment_code: string;
    status: string;
    student: {
        id: number;
        student_code: string;
        full_name: string;
        phone: string | null;
    };
    course: { id: number; name: string };
    duration: {
        months: number;
        starts_on: string | null;
        eligible_on: string | null;
        completed: boolean;
    };
    stages: Stage[];
};
type ApiEnvelope<T> = { data: T };
type RosterResponse = {
    data: RosterStudent[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};
type StatusAction =
    | 'eligible'
    | 'retake'
    | 'paper_submitted'
    | 'pending_result'
    | 'passed'
    | 'failed'
    | 'incomplete'
    | 'absent';
type StatusForm = {
    status: StatusAction;
    exam_name: string;
    exam_date: string;
    total_marks: string;
    obtained_marks: string;
    grade: string;
    remarks: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Academic management', href: '/academy/manage/courses' },
    { title: 'Exam Control', href: '/academy/students/exam-control' },
];
const field =
    'mt-1.5 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/10';
const primary =
    'inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#17375C] px-5 text-sm font-semibold text-white hover:bg-[#254878] disabled:cursor-not-allowed disabled:opacity-50';
const statusLabels: Record<string, string> = {
    eligible: 'Eligible for exam',
    not_eligible: 'Not eligible',
    paper_submitted: 'Paper submitted',
    pending_result: 'Pending result',
    passed: 'Passed',
    failed: 'Failed',
    retake: 'Retake scheduled',
    absent: 'Absent',
    incomplete: 'Incomplete',
};
const localDate = () => {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};
const newStatusForm = (): StatusForm => ({
    status: 'eligible',
    exam_name: '',
    exam_date: localDate(),
    total_marks: '',
    obtained_marks: '',
    grade: '',
    remarks: '',
});
const errorMessage = (error: unknown, fallback: string) =>
    error instanceof ApiRequestError
        ? (Object.values(error.errors ?? {}).flat()[0] ?? error.message)
        : error instanceof Error
          ? error.message
          : fallback;

/** Live exam roster and status workspace backed by Academy ExamControl endpoints. */
export default function ExamControl() {
    const { request } = useApi();
    const [courses, setCourses] = useState<Course[]>([]);
    const [batches, setBatches] = useState<Batch[]>([]);
    const [selectedCourseId, setSelectedCourseId] = useState('');
    const [selectedBatchId, setSelectedBatchId] = useState('');
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [students, setStudents] = useState<RosterStudent[]>([]);
    const [meta, setMeta] = useState({
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
    });
    const [loadingBatches, setLoadingBatches] = useState(true);
    const [loadingRoster, setLoadingRoster] = useState(false);
    const [saving, setSaving] = useState(false);
    const [loadError, setLoadError] = useState('');
    const [formError, setFormError] = useState('');
    const [notice, setNotice] = useState('');
    const [selected, setSelected] = useState<{
        student: RosterStudent;
        stage: Stage;
    } | null>(null);
    const [form, setForm] = useState<StatusForm>(newStatusForm());

    const availableBatches = batches.filter(
        (batch) => String(batch.course_id) === selectedCourseId,
    );
    const selectedBatch =
        batches.find((batch) => String(batch.id) === selectedBatchId) ?? null;
    const examStages = useMemo(
        () =>
            students.flatMap((student) =>
                student.stages.map((stage) => ({ student, stage })),
            ),
        [students],
    );

    const loadBatches = useCallback(
        async (signal?: AbortSignal) => {
            setLoadingBatches(true);
            try {
                const response = await request<
                    ApiEnvelope<{ courses: Course[]; batches: Batch[] }>
                >('/exam-control/batches', { signal });
                const courseOptions = response.data.data.courses;
                const batchOptions = response.data.data.batches;
                setCourses(courseOptions);
                setBatches(batchOptions);
                setSelectedCourseId(
                    (current) => current || String(courseOptions[0]?.id ?? ''),
                );
                setSelectedBatchId(
                    (current) => current || String(batchOptions[0]?.id ?? ''),
                );
                setLoadError('');
            } catch (error) {
                if (signal?.aborted) return;
                setLoadError(errorMessage(error, 'Unable to load batches.'));
            } finally {
                if (!signal?.aborted) setLoadingBatches(false);
            }
        },
        [request],
    );

    const loadRoster = useCallback(
        async (signal?: AbortSignal) => {
            if (!selectedBatchId) {
                setStudents([]);
                setMeta({
                    current_page: 1,
                    last_page: 1,
                    per_page: 20,
                    total: 0,
                });
                return;
            }
            setLoadingRoster(true);
            try {
                const params = new URLSearchParams({
                    batch_id: selectedBatchId,
                    search,
                    page: String(page),
                    per_page: '20',
                });
                const response = await request<RosterResponse>(
                    `/exam-control/students?${params.toString()}`,
                    { signal },
                );
                setStudents(response.data.data);
                setMeta(response.data.meta);
                setLoadError('');
            } catch (error) {
                if (signal?.aborted) return;
                setStudents([]);
                setLoadError(
                    errorMessage(
                        error,
                        'Unable to load students for this batch.',
                    ),
                );
            } finally {
                if (!signal?.aborted) setLoadingRoster(false);
            }
        },
        [page, request, search, selectedBatchId],
    );

    useEffect(() => {
        if (
            selectedBatchId &&
            !availableBatches.some(
                (batch) => String(batch.id) === selectedBatchId,
            )
        ) {
            setSelectedBatchId('');
        }
        // The course selection controls batch options, so use the course ID as the stable dependency.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedCourseId, selectedBatchId]);

    useEffect(() => {
        setPage(1);
        setSelected(null);
    }, [selectedCourseId, selectedBatchId]);

    useEffect(() => {
        const controller = new AbortController();
        void loadBatches(controller.signal);
        return () => controller.abort();
    }, [loadBatches]);

    useEffect(() => {
        const controller = new AbortController();
        void loadRoster(controller.signal);
        return () => controller.abort();
    }, [loadRoster]);

    function submitSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setPage(1);
        setSearch(searchInput.trim());
        setSelected(null);
        setNotice('');
    }

    function openStatus(student: RosterStudent, stage: Stage) {
        const latest = stage.latest_exam;
        const form = newStatusForm();
        const actions = stageActions(stage);
        if (latest && actions.length > 0) {
            form.status = actions[0];
            form.exam_name = latest.exam_name;
            form.exam_date = latest.exam_date?.slice(0, 10) ?? localDate();
            form.total_marks = latest.total_marks ?? '';
            form.obtained_marks = latest.obtained_marks ?? '';
            form.grade = latest.grade ?? '';
            form.remarks = latest.remarks ?? '';
        }
        setSelected({ student, stage });
        setForm(form);
        setFormError('');
        setNotice('');
    }

    async function saveStatus(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!selected) return;
        setSaving(true);
        setFormError('');
        try {
            const payload: Record<string, string | number | null> = {
                status: form.status,
                remarks: form.remarks || null,
            };
            if (form.status === 'eligible' || form.status === 'retake') {
                payload.exam_name = form.exam_name;
                payload.exam_date = form.exam_date;
                payload.total_marks = form.total_marks
                    ? Number(form.total_marks)
                    : null;
            }
            if (['passed', 'failed', 'incomplete'].includes(form.status)) {
                payload.obtained_marks = form.obtained_marks
                    ? Number(form.obtained_marks)
                    : null;
                payload.grade = form.grade || null;
            }
            const response = await request<ApiEnvelope<{ status: string }>>(
                `/exam-control/stages/${selected.stage.stage_record_id}/status`,
                {
                    method: 'PATCH',
                    data: payload,
                },
            );
            setNotice(
                `Exam status updated to “${statusLabels[response.data.data.status] ?? response.data.data.status}” for ${selected.student.student.full_name}.`,
            );
            setSelected(null);
            await loadRoster();
        } catch (error) {
            setFormError(errorMessage(error, 'Unable to update exam status.'));
        } finally {
            setSaving(false);
        }
    }

    const stageActions = (stage: Stage): StatusAction[] => {
        const latest = stage.latest_exam;
        if (!latest) return [];
        if (latest.result_status === 'passed') return [];
        if (latest.result_status === 'failed')
            return stage.can_schedule ? ['retake'] : [];
        if (latest.result_status === 'incomplete')
            return stage.can_schedule ? ['retake'] : [];
        if (latest.attempt_status === 'scheduled')
            return ['paper_submitted', 'absent'];
        if (latest.attempt_status === 'appeared')
            return ['pending_result', 'passed', 'failed', 'incomplete'];
        if (latest.attempt_status === 'absent')
            return stage.can_schedule ? ['retake'] : [];
        return [];
    };

    const canEditStage = (stage: Stage): boolean =>
        stage.can_schedule || stageActions(stage).length > 0;

    const statCards = [
        { label: 'Students in this page', value: students.length, icon: Users },
        { label: 'Exam stages', value: examStages.length, icon: ClipboardList },
        {
            label: 'Passed exams',
            value: examStages.filter(
                ({ stage }) => stage.latest_exam?.result_status === 'passed',
            ).length,
            icon: CheckCircle2,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Exam Control" />
            <main className="min-h-screen bg-[#F7F9FC] px-4 py-6 text-slate-800 sm:px-7 lg:px-10">
                <div className="mx-auto max-w-7xl space-y-6">
                    <header className="rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-6 text-white shadow-lg sm:p-8">
                        <div className="flex items-center gap-2 text-xs font-semibold tracking-[0.16em] text-cyan-100 uppercase">
                            <FileCheck2 size={16} /> Academic management
                        </div>
                        <h1 className="mt-3 text-3xl font-semibold tracking-tight">
                            Exam Control
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm text-cyan-50/85">
                            Search students by name or ID, review course stages,
                            and manage eligibility, submissions, results, and
                            retakes.
                        </p>
                    </header>

                    <div className="grid gap-4 sm:grid-cols-3">
                        {statCards.map(({ label, value, icon: Icon }) => (
                            <div
                                key={label}
                                className="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5"
                            >
                                <span className="rounded-xl bg-teal-50 p-3 text-teal-700">
                                    <Icon size={21} />
                                </span>
                                <div>
                                    <p className="text-xs text-slate-500">
                                        {label}
                                    </p>
                                    <p className="mt-1 text-2xl font-semibold text-[#17375C]">
                                        {value}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>

                    <section
                        aria-labelledby="student-search-title"
                        className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"
                    >
                        <h2
                            id="student-search-title"
                            className="text-lg font-semibold text-[#17375C]"
                        >
                            Find students
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Choose a batch, then search by student name or
                            student ID.
                        </p>
                        <form
                            onSubmit={submitSearch}
                            className="mt-5 grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto]"
                        >
                            <label className="text-xs font-medium text-slate-600">
                                Course
                                <select
                                    required
                                    value={selectedCourseId}
                                    onChange={(event) => {
                                        setSelectedCourseId(event.target.value);
                                        setSelectedBatchId('');
                                    }}
                                    className={field}
                                    disabled={loadingBatches || !courses.length}
                                >
                                    <option value="">
                                        {loadingBatches
                                            ? 'Loading courses...'
                                            : courses.length
                                              ? 'Select a course'
                                              : 'No courses available'}
                                    </option>
                                    {courses.map((course) => (
                                        <option
                                            key={course.id}
                                            value={course.id}
                                        >
                                            {course.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="text-xs font-medium text-slate-600">
                                Batch
                                <select
                                    required
                                    value={selectedBatchId}
                                    onChange={(event) =>
                                        setSelectedBatchId(event.target.value)
                                    }
                                    className={field}
                                    disabled={
                                        !selectedCourseId ||
                                        !availableBatches.length
                                    }
                                >
                                    <option value="">
                                        {availableBatches.length
                                            ? 'Select a batch'
                                            : 'No batches for this course'}
                                    </option>
                                    {availableBatches.map((batch) => (
                                        <option key={batch.id} value={batch.id}>
                                            {batch.name} ({batch.code})
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="text-xs font-medium text-slate-600">
                                Student name or ID
                                <input
                                    value={searchInput}
                                    onChange={(event) =>
                                        setSearchInput(event.target.value)
                                    }
                                    placeholder="e.g. CMUD-1001 or Ayesha Rahman"
                                    className={field}
                                />
                            </label>
                            <button
                                type="submit"
                                className={primary}
                                disabled={!selectedBatchId || loadingRoster}
                            >
                                <Search size={16} /> Search students
                            </button>
                        </form>
                        {selectedBatch && (
                            <p className="mt-3 text-xs text-slate-500">
                                {selectedBatch.course.duration_months} month
                                course / Batch starts{' '}
                                {dateLabel(selectedBatch.starts_on)} ·{' '}
                                {selectedBatch.branch.name}
                            </p>
                        )}
                    </section>

                    {notice && (
                        <p
                            role="status"
                            className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                        >
                            {notice}
                        </p>
                    )}
                    {loadError && (
                        <p
                            role="alert"
                            className="flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
                        >
                            <AlertCircle
                                size={17}
                                className="mt-0.5 shrink-0"
                            />
                            {loadError}
                        </p>
                    )}

                    <section
                        aria-labelledby="roster-title"
                        className="overflow-hidden rounded-2xl border border-slate-200 bg-white"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-5">
                            <div>
                                <h2
                                    id="roster-title"
                                    className="font-semibold text-[#17375C]"
                                >
                                    Student exam roster{' '}
                                    <span className="ml-2 text-sm font-normal text-slate-400">
                                        {meta.total} students
                                    </span>
                                </h2>
                                <p className="mt-1 text-xs text-slate-500">
                                    Exam status is tracked for each required
                                    course stage.
                                </p>
                            </div>
                            {loadingRoster && (
                                <LoaderCircle
                                    size={18}
                                    className="animate-spin text-teal-700"
                                    aria-label="Loading roster"
                                />
                            )}
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[860px] text-left text-sm">
                                <thead className="bg-slate-50 text-xs text-slate-500">
                                    <tr>
                                        {[
                                            'Student',
                                            'Course duration',
                                            'Course stage',
                                            'Exam status',
                                            'Latest attempt',
                                            'Action',
                                        ].map((label) => (
                                            <th
                                                key={label}
                                                className="px-5 py-3 font-medium"
                                            >
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {students.flatMap((student) =>
                                        student.stages.map((stage) => {
                                            const actions = stageActions(stage);
                                            const canEdit = canEditStage(stage);
                                            return (
                                                <tr
                                                    key={`${student.enrollment_id}-${stage.stage_record_id}`}
                                                >
                                                    <td className="px-5 py-4">
                                                        <p className="font-semibold whitespace-nowrap text-[#17375C]">
                                                            {
                                                                student.student
                                                                    .full_name
                                                            }
                                                        </p>
                                                        <p className="mt-1 font-mono text-xs text-slate-500">
                                                            {
                                                                student.student
                                                                    .student_code
                                                            }{' '}
                                                            ·{' '}
                                                            {
                                                                student.enrollment_code
                                                            }
                                                        </p>
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <StatusBadge
                                                            status={
                                                                student.duration
                                                                    .completed
                                                                    ? 'Complete'
                                                                    : 'In progress'
                                                            }
                                                            good={
                                                                student.duration
                                                                    .completed
                                                            }
                                                        />
                                                        <p className="mt-1 text-xs whitespace-nowrap text-slate-500">
                                                            {student.duration
                                                                .completed
                                                                ? `Completed ${dateLabel(student.duration.eligible_on)}`
                                                                : `Eligible ${dateLabel(student.duration.eligible_on)}`}
                                                        </p>
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <p className="font-medium text-slate-700">
                                                            {stage.stage.name}
                                                        </p>
                                                        <p className="mt-1 text-xs text-slate-500">
                                                            Stage:{' '}
                                                            {stage.stage_status}
                                                        </p>
                                                        {stage.exam_eligibility
                                                            .reasons.length >
                                                            0 && (
                                                            <p className="mt-1 max-w-xs text-xs text-amber-700">
                                                                {
                                                                    stage
                                                                        .exam_eligibility
                                                                        .reasons[0]
                                                                }
                                                            </p>
                                                        )}
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <StatusBadge
                                                            status={
                                                                statusLabels[
                                                                    stage
                                                                        .exam_status
                                                                ] ??
                                                                stage.exam_status
                                                            }
                                                            good={
                                                                stage.exam_status ===
                                                                'passed'
                                                            }
                                                        />
                                                    </td>
                                                    <td className="px-5 py-4 text-xs text-slate-600">
                                                        {stage.latest_exam ? (
                                                            <>
                                                                <p className="font-medium">
                                                                    {
                                                                        stage
                                                                            .latest_exam
                                                                            .exam_name
                                                                    }{' '}
                                                                    / Attempt{' '}
                                                                    {
                                                                        stage
                                                                            .latest_exam
                                                                            .attempt_number
                                                                    }
                                                                </p>
                                                                <p className="mt-1">
                                                                    {dateLabel(
                                                                        stage
                                                                            .latest_exam
                                                                            .exam_date,
                                                                    )}
                                                                    {stage
                                                                        .latest_exam
                                                                        .obtained_marks !==
                                                                        null &&
                                                                        ` / ${stage.latest_exam.obtained_marks}/${stage.latest_exam.total_marks ?? '—'}`}
                                                                </p>
                                                            </>
                                                        ) : (
                                                            'No exam recorded'
                                                        )}
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        {canEdit ? (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    openStatus(
                                                                        student,
                                                                        stage,
                                                                    )
                                                                }
                                                                className="rounded-lg border border-teal-200 bg-teal-50 px-3 py-2 text-xs font-semibold whitespace-nowrap text-teal-800 hover:bg-teal-100"
                                                            >
                                                                {!stage.latest_exam &&
                                                                stage.can_schedule
                                                                    ? 'Schedule exam'
                                                                    : actions[0] ===
                                                                        'retake'
                                                                      ? 'Schedule retake'
                                                                      : 'Update status'}
                                                                <span className="sr-only">
                                                                    {' '}
                                                                    for{' '}
                                                                    {
                                                                        student
                                                                            .student
                                                                            .full_name
                                                                    }
                                                                    ,{' '}
                                                                    {
                                                                        stage
                                                                            .stage
                                                                            .name
                                                                    }
                                                                </span>
                                                            </button>
                                                        ) : (
                                                            <span className="text-xs text-slate-400">
                                                                {stage.latest_exam
                                                                    ? 'No action'
                                                                    : 'Not eligible'}
                                                            </span>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        }),
                                    )}
                                    {!loadingRoster &&
                                        students.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={6}
                                                    className="px-6 py-12 text-center text-slate-500"
                                                >
                                                    {selectedBatchId
                                                        ? 'No enrolled students match this batch and search.'
                                                        : 'Select a batch to load its students.'}
                                                </td>
                                            </tr>
                                        )}
                                </tbody>
                            </table>
                        </div>
                        {meta.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm text-slate-600">
                                <span>
                                    Page {meta.current_page} of {meta.last_page}
                                </span>
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        disabled={loadingRoster || page <= 1}
                                        onClick={() =>
                                            setPage((current) =>
                                                Math.max(1, current - 1),
                                            )
                                        }
                                        aria-label="Previous page"
                                        className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 disabled:text-slate-300"
                                    >
                                        <ChevronLeft size={17} />
                                    </button>
                                    <button
                                        type="button"
                                        disabled={
                                            loadingRoster ||
                                            page >= meta.last_page
                                        }
                                        onClick={() =>
                                            setPage((current) =>
                                                Math.min(
                                                    meta.last_page,
                                                    current + 1,
                                                ),
                                            )
                                        }
                                        aria-label="Next page"
                                        className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 disabled:text-slate-300"
                                    >
                                        <ChevronRight size={17} />
                                    </button>
                                </div>
                            </div>
                        )}
                    </section>

                    <Dialog
                        open={selected !== null}
                        onOpenChange={(open) => {
                            if (!open && !saving) setSelected(null);
                        }}
                    >
                        {selected && (
                            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                                <DialogHeader>
                                    <DialogTitle className="text-[#17375C]">
                                        {selected.stage.latest_exam
                                            ? 'Update exam status'
                                            : 'Schedule exam'}
                                    </DialogTitle>
                                    <DialogDescription>
                                        Change the exam record for this selected
                                        student and course stage.
                                    </DialogDescription>
                                </DialogHeader>
                                <form
                                    onSubmit={saveStatus}
                                    className="space-y-5"
                                >
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        <label className="text-xs font-medium text-slate-600">
                                            Exam status
                                            <select
                                                value={form.status}
                                                onChange={(event) =>
                                                    setForm((current) => ({
                                                        ...current,
                                                        status: event.target
                                                            .value as StatusAction,
                                                    }))
                                                }
                                                className={field}
                                            >
                                                {(selected.stage.latest_exam
                                                    ? stageActions(
                                                          selected.stage,
                                                      )
                                                    : selected.stage
                                                            .can_schedule
                                                      ? [
                                                            'eligible' as StatusAction,
                                                        ]
                                                      : []
                                                ).map((status) => (
                                                    <option
                                                        key={status}
                                                        value={status}
                                                    >
                                                        {statusLabels[status]}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>
                                        {(form.status === 'eligible' ||
                                            form.status === 'retake') && (
                                            <>
                                                <label className="text-xs font-medium text-slate-600">
                                                    Exam name
                                                    <input
                                                        required
                                                        maxLength={255}
                                                        value={form.exam_name}
                                                        onChange={(event) =>
                                                            setForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    exam_name:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                        placeholder="Course final exam"
                                                        className={field}
                                                    />
                                                </label>
                                                <label className="text-xs font-medium text-slate-600">
                                                    Exam date
                                                    <input
                                                        required
                                                        type="date"
                                                        value={form.exam_date}
                                                        onChange={(event) =>
                                                            setForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    exam_date:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                        className={field}
                                                    />
                                                </label>
                                                <label className="text-xs font-medium text-slate-600">
                                                    Total marks
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        value={form.total_marks}
                                                        onChange={(event) =>
                                                            setForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    total_marks:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                        className={field}
                                                    />
                                                </label>
                                            </>
                                        )}
                                        {[
                                            'passed',
                                            'failed',
                                            'incomplete',
                                        ].includes(form.status) && (
                                            <>
                                                <label className="text-xs font-medium text-slate-600">
                                                    Marks obtained
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max={
                                                            selected.stage
                                                                .latest_exam
                                                                ?.total_marks ??
                                                            undefined
                                                        }
                                                        step="0.01"
                                                        value={
                                                            form.obtained_marks
                                                        }
                                                        onChange={(event) =>
                                                            setForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    obtained_marks:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                        className={field}
                                                    />
                                                </label>
                                                <label className="text-xs font-medium text-slate-600">
                                                    Grade
                                                    <input
                                                        maxLength={50}
                                                        value={form.grade}
                                                        onChange={(event) =>
                                                            setForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    grade: event
                                                                        .target
                                                                        .value,
                                                                }),
                                                            )
                                                        }
                                                        className={field}
                                                    />
                                                </label>
                                            </>
                                        )}
                                    </div>
                                    <label className="block text-xs font-medium text-slate-600">
                                        Remarks
                                        <textarea
                                            rows={3}
                                            maxLength={10000}
                                            value={form.remarks}
                                            onChange={(event) =>
                                                setForm((current) => ({
                                                    ...current,
                                                    remarks: event.target.value,
                                                }))
                                            }
                                            placeholder="Optional exam notes"
                                            className={`${field} h-auto py-3`}
                                        />
                                    </label>
                                    {formError && (
                                        <p
                                            role="alert"
                                            className="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700"
                                        >
                                            {formError}
                                        </p>
                                    )}
                                    <div className="flex justify-end gap-3 border-t border-slate-100 pt-4">
                                        <button
                                            type="button"
                                            onClick={() => setSelected(null)}
                                            disabled={saving}
                                            className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={saving}
                                            className={primary}
                                        >
                                            {saving && (
                                                <LoaderCircle
                                                    size={16}
                                                    className="animate-spin"
                                                />
                                            )}
                                            {saving ? 'Saving…' : 'Save status'}
                                        </button>
                                    </div>
                                </form>
                            </DialogContent>
                        )}
                    </Dialog>
                </div>
            </main>
        </AppLayout>
    );
}

function StatusBadge({ status, good }: { status: string; good: boolean }) {
    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap ${good ? 'bg-emerald-50 text-emerald-700' : status === 'Not eligible' || status === 'Failed' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600'}`}
        >
            {status}
        </span>
    );
}

function dateLabel(date?: string | null) {
    if (!date) return '—';
    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${date.slice(0, 10)}T00:00:00`));
}
