import { useEffect, useState, type FormEvent } from 'react';
import { Head } from '@inertiajs/react';
import { BriefcaseBusiness, Eye, Pencil, Plus, Trash2, UserPlus } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useApi, ApiRequestError } from '@/hooks/use-api';
import AppLayout from '@/layouts/app-layout';

type StuffAssignment = {
    id: number;
    branch: { id: number; name: string; code?: string } | null;
    department: { id: number; name: string; code?: string } | null;
};

type StuffApiRecord = {
    id: number;
    institute_id: number;
    user_id: number | null;
    stuff_code: string;
    full_name: string;
    phone: string;
    email: string | null;
    nid_number: string | null;
    address: string | null;
    date_of_birth: string | null;
    designation: string;
    joining_date: string;
    leaving_date: string | null;
    employment_status: string;
    notes: string | null;
    institute?: { id: number; name: string } | null;
    user?: { id: number; name: string; email: string } | null;
    stuff_assignments?: StuffAssignment[];
    teachers?: Array<{
        id: number;
        teacher_code: string;
        full_name: string;
        designation: string | null;
        is_active: boolean;
    }>;
    institute_expenses_count?: number;
    has_nid_file: boolean;
    nid_file_download_url: string | null;
    photo_url: string | null;
};

type StuffRow = {
    id: number;
    code: string;
    name: string;
    phone: string;
    email: string;
    nidNumber: string;
    hasNidFile: boolean;
    photoUrl: string | null;
    hasPhoto: boolean;
    designation: string;
    joined: string;
    status: string;
    address: string;
    notes: string;
    assignments: Array<{ branch: string; department: string }>;
};

type BranchTotal = { id: number; name: string; stuff_count: number };
type InstituteOption = {
    id: number;
    name: string;
    branches: Array<{ id: number; name: string; code: string }>;
    departments: Array<{ id: number; name: string; code: string }>;
};
type StuffOptionsResponse = { data: InstituteOption[] };
type StuffListResponse = {
    data: StuffApiRecord[];
    meta: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total_stuff: number;
        branch_totals: BranchTotal[];
    };
};
type StuffEnvelope = { data: StuffApiRecord; message?: string };
type StuffUpdateForm = {
    stuff_code: string;
    full_name: string;
    phone: string;
    email: string;
    nid_number: string;
    designation: string;
    joining_date: string;
    employment_status: string;
    date_of_birth: string;
    leaving_date: string;
    address: string;
    notes: string;
    nid_file: File | null;
    remove_nid_file: boolean;
    photo: File | null;
    remove_photo: boolean;
};

const statuses = [
    { value: 'active', label: 'Active' },
    { value: 'on_leave', label: 'On leave' },
    { value: 'inactive', label: 'Inactive' },
];
const emptyForm: StuffUpdateForm = {
    stuff_code: '',
    full_name: '',
    phone: '',
    email: '',
    nid_number: '',
    designation: '',
    joining_date: '',
    employment_status: 'active',
    date_of_birth: '',
    leaving_date: '',
    address: '',
    notes: '',
    nid_file: null,
    remove_nid_file: false,
    photo: null,
    remove_photo: false,
};
const field =
    'mt-2 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm focus:outline-teal-600';
const primary =
    'rounded-xl bg-[#17375C] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#254878] disabled:cursor-not-allowed disabled:opacity-60';

/** Convert the API model and loaded relationships into the fields used by the directory. */
function toStuffRow(record: StuffApiRecord): StuffRow {
    return {
        id: record.id,
        code: record.stuff_code,
        name: record.full_name,
        phone: record.phone,
        email: record.email ?? '',
        nidNumber: record.nid_number ?? '',
        hasNidFile: record.has_nid_file,
        photoUrl: record.photo_url,
        hasPhoto: Boolean(record.photo_url),
        designation: record.designation,
        joined: record.joining_date?.slice(0, 10) ?? '',
        status: record.employment_status,
        address: record.address ?? '',
        notes: record.notes ?? '',
        assignments: (record.stuff_assignments ?? []).map((assignment) => ({
            branch: assignment.branch?.name ?? 'Unknown branch',
            department: assignment.department?.name ?? 'Unknown department',
        })),
    };
}

/** Translate known persisted status values into short labels for stuff. */
function statusLabel(status: string): string {
    return statuses.find((option) => option.value === status)?.label ?? status;
}

/** Show consistent label/value pairs throughout the stuff detail view. */
function DetailItem({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs font-medium text-slate-500">{label}</dt>
            <dd className="mt-1 break-words text-sm font-medium text-slate-800">{value}</dd>
        </div>
    );
}

/** Build a compact monogram for profiles that do not have a stuff photo. */
function stuffInitials(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0] ?? '')
        .join('')
        .toUpperCase();
}

/** Surface Laravel validation messages as well as transport and server errors. */
function requestErrorMessage(error: unknown): string {
    if (error instanceof ApiRequestError) {
        const validationMessages = Object.values(error.errors ?? {}).flat();
        return validationMessages.length
            ? `${error.message} ${validationMessages.join(' ')}`
            : error.message;
    }

    return error instanceof Error ? error.message : 'The request could not be completed.';
}

export default function StuffPage() {
    const { request } = useApi();
    const [stuff, setStuff] = useState<StuffRow[]>([]);
    const [branchTotals, setBranchTotals] = useState<BranchTotal[]>([]);
    const [stuffOptions, setStuffOptions] = useState<InstituteOption[]>([]);
    const [optionsLoading, setOptionsLoading] = useState(true);
    const [optionsError, setOptionsError] = useState('');
    const [createInstituteId, setCreateInstituteId] = useState('');
    const [createAssignments, setCreateAssignments] = useState<Array<{ branch_id: string; department_id: string }>>([]);
    const [totalStuff, setTotalStuff] = useState<number | null>(null);
    const [query, setQuery] = useState('');
    const [debouncedQuery, setDebouncedQuery] = useState('');
    const [status, setStatus] = useState('All');
    const [branchId, setBranchId] = useState('All');
    const [currentPage, setCurrentPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [resultCount, setResultCount] = useState(0);
    const [refreshKey, setRefreshKey] = useState(0);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [requestError, setRequestError] = useState('');
    const [formError, setFormError] = useState('');
    const [notice, setNotice] = useState('');
    const [dialog, setDialog] = useState<'create' | 'edit' | 'view' | null>(null);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [profile, setProfile] = useState<StuffApiRecord | null>(null);
    const [profileLoading, setProfileLoading] = useState(false);
    const [profileError, setProfileError] = useState('');
    const [form, setForm] = useState<StuffUpdateForm>(emptyForm);
    const [hasNidFile, setHasNidFile] = useState(false);
    const [hasPhoto, setHasPhoto] = useState(false);
    const selectedStuff = stuff.find((item) => item.id === selectedId);
    const createInstitute = stuffOptions.find((option) => String(option.id) === createInstituteId);

    // Load form choices from the module API so assignments stay tied to the chosen institute.
    useEffect(() => {
        const controller = new AbortController();
        setOptionsLoading(true);
        setOptionsError('');

        request<StuffOptionsResponse>('/stuff/options', {
            method: 'GET',
            signal: controller.signal,
        })
            .then(({ data }) => {
                setStuffOptions(data.data);
                setCreateInstituteId((current) => current || String(data.data[0]?.id ?? ''));
            })
            .catch((error: unknown) => {
                if (!controller.signal.aborted) setOptionsError(requestErrorMessage(error));
            })
            .finally(() => {
                if (!controller.signal.aborted) setOptionsLoading(false);
            });

        return () => controller.abort();
    }, [request]);

    // Debounce free-text search so typing does not issue one request per keystroke.
    useEffect(() => {
        const timer = window.setTimeout(() => setDebouncedQuery(query.trim()), 300);

        return () => window.clearTimeout(timer);
    }, [query]);

    // Fetch server-filtered directory data; abort in-flight requests when filters or page change.
    useEffect(() => {
        const controller = new AbortController();
        const params: Record<string, string | number> = {
            page: currentPage,
            per_page: 15,
        };

        if (debouncedQuery) {
            if (/^\d+$/.test(debouncedQuery)) {
                params.id = Number(debouncedQuery);
            } else {
                params.search = debouncedQuery;
            }
        }
        if (branchId !== 'All') params.branch_id = branchId;
        if (status !== 'All') params.employment_status = status;

        setLoading(true);
        setRequestError('');

        request<StuffListResponse>('/stuff', {
            method: 'GET',
            params,
            signal: controller.signal,
        })
            .then(({ data }) => {
                setStuff(data.data.map(toStuffRow));
                setBranchTotals(data.meta.branch_totals);
                setTotalStuff(data.meta.total_stuff);
                setLastPage(data.meta.last_page);
                setResultCount(data.meta.total);
            })
            .catch((error: unknown) => {
                if (!controller.signal.aborted) setRequestError(requestErrorMessage(error));
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [branchId, currentPage, debouncedQuery, refreshKey, request, status]);

    // Load the full server profile when View is opened, including relationships omitted from rows.
    useEffect(() => {
        if (dialog !== 'view' || selectedId === null) return;

        const controller = new AbortController();
        setProfile(null);
        setProfileError('');
        setProfileLoading(true);

        request<StuffEnvelope>(`/stuff/${selectedId}`, {
            method: 'GET',
            signal: controller.signal,
        })
            .then(({ data }) => setProfile(data.data))
            .catch((error: unknown) => {
                if (!controller.signal.aborted) setProfileError(requestErrorMessage(error));
            })
            .finally(() => {
                if (!controller.signal.aborted) setProfileLoading(false);
            });

        return () => controller.abort();
    }, [dialog, request, selectedId]);

    function openEdit(item: StuffRow) {
        setSelectedId(item.id);
        setForm({
            stuff_code: item.code,
            full_name: item.name,
            phone: item.phone,
            email: item.email,
            nid_number: item.nidNumber,
            designation: item.designation,
            joining_date: item.joined,
            employment_status: item.status,
            date_of_birth: '',
            leaving_date: '',
            address: item.address,
            notes: item.notes,
            nid_file: null,
            remove_nid_file: false,
            photo: null,
            remove_photo: false,
        });
        setHasNidFile(item.hasNidFile);
        setHasPhoto(item.hasPhoto);
        setFormError('');
        setDialog('edit');
    }

    function openView(item: StuffRow) {
        setSelectedId(item.id);
        setDialog('view');
    }

    /** Prepare a blank create form with the first available institute selected. */
    function openCreate() {
        setSelectedId(null);
        setForm(emptyForm);
        setCreateInstituteId(String(stuffOptions[0]?.id ?? ''));
        setCreateAssignments([]);
        setHasNidFile(false);
        setHasPhoto(false);
        setFormError('');
        setOptionsError('');
        setDialog('create');
    }

    async function save(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const isCreating = dialog === 'create';
        if (!isCreating && selectedId === null) return;

        if (isCreating && createAssignments.some((assignment) => !assignment.branch_id || !assignment.department_id)) {
            setFormError('Choose both a branch and department for each assignment, or remove the incomplete row.');
            return;
        }

        // Method spoofing lets PHP parse the multipart NID upload as a normal form POST.
        const payload = new FormData();
        if (!isCreating) payload.append('_method', 'PUT');
        const editableFields = [
            'stuff_code', 'full_name', 'phone', 'email', 'nid_number', 'designation',
            'joining_date', 'employment_status', 'date_of_birth', 'leaving_date', 'address', 'notes',
        ] as const;
        editableFields.forEach((key) => {
            if (key === 'date_of_birth' || key === 'leaving_date') {
                if (isCreating || form[key]) payload.append(key, form[key].trim());
            } else {
                payload.append(key, form[key].trim());
            }
        });
        if (isCreating) {
            payload.append('institute_id', createInstituteId);
            createAssignments.forEach((assignment, index) => {
                payload.append(`assignments[${index}][branch_id]`, assignment.branch_id);
                payload.append(`assignments[${index}][department_id]`, assignment.department_id);
            });
        }
        if (form.nid_file) payload.append('nid_file', form.nid_file);
        if (form.photo) payload.append('photo', form.photo);
        if (!isCreating && form.remove_nid_file) payload.append('remove_nid_file', '1');
        if (!isCreating && form.remove_photo) payload.append('remove_photo', '1');

        setSaving(true);
        setFormError('');
        setNotice('');

        try {
            const { data } = await request<StuffEnvelope>(isCreating ? '/stuff' : `/stuff/${selectedId}`, {
                method: 'POST',
                data: payload,
            });

            if (isCreating) {
                setCurrentPage(1);
                setRefreshKey((current) => current + 1);
            } else {
                setStuff((rows) =>
                    rows.map((row) => (row.id === data.data.id ? toStuffRow(data.data) : row)),
                );
                setHasNidFile(data.data.has_nid_file);
                setHasPhoto(Boolean(data.data.photo_url));
            }
            setDialog(null);
            setNotice(data.message ?? (isCreating ? 'Stuff member created.' : 'Stuff information updated.'));
        } catch (error) {
            setFormError(requestErrorMessage(error));
        } finally {
            setSaving(false);
        }
    }

    function updateFilter(callback: () => void) {
        callback();
        setCurrentPage(1);
        setNotice('');
    }

    return (
        <AppLayout breadcrumbs={[{ title: 'Stuff', href: '/stuff' }]}>
            <Head title="Stuff" />
            <main className="min-h-screen bg-[#F7F9FC] px-4 py-6 text-slate-800 sm:px-7 lg:px-10">
                <div className="mx-auto max-w-7xl space-y-6">
                    <header className="rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-5 text-white sm:p-6">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p className="flex items-center gap-2 text-xs font-semibold tracking-widest text-cyan-100 uppercase">
                                    <BriefcaseBusiness size={16} /> Stuff management
                                </p>
                                <h1 className="mt-3 text-3xl font-semibold">Stuff</h1>
                                <p className="mt-2 text-sm text-cyan-50">
                                    Browse stuff profiles and branch assignments.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={openCreate}
                                disabled={optionsLoading}
                                className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-[#17375C] transition hover:bg-cyan-50 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <UserPlus size={17} aria-hidden="true" />
                                Add new employee
                            </button>
                        </div>
                        <div
                            aria-label="Stuff totals by branch"
                            className="mt-5 flex flex-wrap items-center gap-x-8 gap-y-3 border-t border-white/20 pt-4"
                        >
                            <div className="flex items-baseline gap-3">
                                <span className="text-5xl leading-none font-bold tracking-tight sm:text-6xl">
                                    {totalStuff ?? '—'}
                                </span>
                                <span className="text-xs font-medium tracking-wide text-cyan-100 uppercase">
                                    Total employees
                                </span>
                            </div>
                            <div className="flex flex-wrap items-center gap-x-6 gap-y-2">
                                {branchTotals.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex items-baseline gap-2 border-l border-white/25 pl-4"
                                    >
                                        <span className="text-2xl leading-none font-semibold">
                                            {item.stuff_count}
                                        </span>
                                        <span className="text-xs text-cyan-100">{item.name}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </header>

                    {requestError && (
                        <p
                            role="alert"
                            className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"
                        >
                            Could not load stuff records: {requestError}
                        </p>
                    )}
                    {optionsError && (
                        <p role="alert" className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                            Employee form options could not be loaded: {optionsError}
                        </p>
                    )}
                    {notice && (
                        <p
                            role="status"
                            className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
                        >
                            {notice}
                        </p>
                    )}

                    <section
                        aria-label="Stuff filters"
                        className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-3"
                    >
                        <label className="text-sm font-medium">
                            Search
                            <input
                                value={query}
                                onChange={(event) => updateFilter(() => setQuery(event.target.value))}
                                placeholder="Name, stuff code, ID, or phone"
                                className={field}
                            />
                        </label>
                        <label className="text-sm font-medium">
                            Status
                            <select
                                value={status}
                                onChange={(event) =>
                                    updateFilter(() => setStatus(event.target.value))
                                }
                                className={field}
                            >
                                <option value="All">All statuses</option>
                                {statuses.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="text-sm font-medium">
                            Branch
                            <select
                                value={branchId}
                                onChange={(event) =>
                                    updateFilter(() => setBranchId(event.target.value))
                                }
                                className={field}
                            >
                                <option value="All">All branches</option>
                                {branchTotals.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </section>

                    <section
                        className="overflow-hidden rounded-2xl border border-slate-200 bg-white"
                        aria-labelledby="stuff-list"
                    >
                        <h2
                            id="stuff-list"
                            className="border-b border-slate-100 px-6 py-5 font-semibold text-[#17375C]"
                        >
                            Stuff directory{' '}
                            <span className="ml-2 text-sm font-normal text-slate-500">
                                {loading ? 'Loading…' : `${resultCount} records`}
                            </span>
                        </h2>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-slate-50 text-slate-500">
                                    <tr>
                                        {[
                                            'Stuff',
                                            'Contact',
                                            'Designation',
                                            'Assignments',
                                            'Status',
                                            'Actions',
                                        ].map((label) => (
                                            <th key={label} className="px-5 py-3 font-medium">
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {stuff.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-5 py-4 font-medium">
                                                {item.name}
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {item.code}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4">
                                                {item.phone}
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {item.email || 'No email'}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4">{item.designation}</td>
                                            <td className="px-5 py-4">
                                                {item.assignments.length ? (
                                                    item.assignments.map((assignment, index) => (
                                                        <p
                                                            key={`${item.id}-${assignment.branch}-${assignment.department}-${index}`}
                                                            className="py-1 text-xs"
                                                        >
                                                            {assignment.branch} ·{' '}
                                                            {assignment.department}
                                                        </p>
                                                    ))
                                                ) : (
                                                    <span className="text-slate-500">
                                                        Unassigned
                                                    </span>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-5 py-4">
                                                {statusLabel(item.status)}
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="flex gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => openView(item)}
                                                        aria-label={`View ${item.name}`}
                                                        title="View stuff"
                                                        className="rounded-lg border border-slate-200 p-2 text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-[#17375C]"
                                                    >
                                                        <Eye size={16} aria-hidden="true" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => openEdit(item)}
                                                        aria-label={`Update ${item.name}`}
                                                        title="Update stuff"
                                                        className="rounded-lg border border-teal-200 bg-teal-50 p-2 text-teal-800 transition hover:bg-teal-100"
                                                    >
                                                        <Pencil size={16} aria-hidden="true" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {!stuff.length && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-5 py-12 text-center text-slate-500"
                                            >
                                                {loading
                                                    ? 'Loading stuff records…'
                                                    : requestError
                                                      ? 'Stuff records are unavailable.'
                                                      : 'No stuff records match these filters.'}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="flex items-center justify-between border-t border-slate-100 px-5 py-4 text-sm text-slate-500">
                            <span>
                                {resultCount === 0
                                    ? 'No records'
                                    : `Showing ${((currentPage - 1) * 15) + 1}–${Math.min(currentPage * 15, resultCount)} of ${resultCount}`}
                            </span>
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
                                    disabled={loading || currentPage <= 1}
                                    className="rounded-lg border border-slate-200 px-3 py-2 disabled:opacity-40"
                                >
                                    Previous
                                </button>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setCurrentPage((page) => Math.min(lastPage, page + 1))
                                    }
                                    disabled={loading || currentPage >= lastPage}
                                    className="rounded-lg border border-slate-200 px-3 py-2 disabled:opacity-40"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </main>

            <Dialog
                open={dialog !== null}
                onOpenChange={(open) => {
                    if (!open) setDialog(null);
                }}
            >
                <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>
                            {dialog === 'view'
                                ? 'Employee profile'
                                : dialog === 'create' ? 'Add new employee' : 'Update stuff'}
                        </DialogTitle>
                        <DialogDescription>
                            {dialog === 'view'
                                ? 'Personal details, employment, and institute relationships.'
                                : dialog === 'create' ? 'Enter employee details and optional branch assignments.' : 'Update the stuff profile information.'}
                        </DialogDescription>
                    </DialogHeader>

                    {dialog === 'view' && (
                        <>
                            {profileLoading && (
                                <p className="text-sm text-slate-500">Loading stuff profile…</p>
                            )}
                            {profileError && (
                                <p role="alert" className="text-sm text-rose-700">
                                    Could not load stuff profile: {profileError}
                                </p>
                            )}
                            {profile && (
                                <div className="space-y-5">
                                    <section className="flex flex-wrap items-center gap-4 rounded-2xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-5 text-white">
                                        <div className="flex size-16 items-center justify-center rounded-2xl bg-white/15 text-xl font-semibold ring-1 ring-white/25">
                                            {profile.photo_url ? (
                                                <img
                                                    src={profile.photo_url}
                                                    alt={`${profile.full_name} profile`}
                                                    className="size-16 rounded-2xl object-cover"
                                                />
                                            ) : (
                                                stuffInitials(profile.full_name)
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <h2 className="truncate text-xl font-semibold">
                                                {profile.full_name}
                                            </h2>
                                            <p className="mt-1 text-sm text-cyan-100">
                                                {profile.designation}
                                            </p>
                                            <p className="mt-2 text-xs text-cyan-100">
                                                Stuff code: {profile.stuff_code} <span className="mx-1">·</span>
                                                Record ID: {profile.id}
                                            </p>
                                        </div>
                                        <span className="rounded-full border border-white/25 bg-white/10 px-3 py-1.5 text-xs font-semibold">
                                            {statusLabel(profile.employment_status)}
                                        </span>
                                    </section>

                                    <section className="rounded-2xl border border-slate-200 bg-white p-5">
                                        <h3 className="text-sm font-semibold text-[#17375C]">
                                            Contact and personal information
                                        </h3>
                                        <dl className="mt-4 grid gap-x-6 gap-y-5 sm:grid-cols-2">
                                            <DetailItem label="Phone" value={profile.phone || 'Not provided'} />
                                            <DetailItem label="Email" value={profile.email || 'Not provided'} />
                                            <DetailItem label="Date of birth" value={profile.date_of_birth?.slice(0, 10) ?? 'Not provided'} />
                                            <DetailItem label="Address" value={profile.address || 'Not provided'} />
                                        </dl>
                                    </section>

                                    <section className="rounded-2xl border border-slate-200 bg-white p-5">
                                        <h3 className="text-sm font-semibold text-[#17375C]">
                                            Employment and assignments
                                        </h3>
                                        <dl className="mt-4 grid gap-x-6 gap-y-5 sm:grid-cols-2">
                                            <DetailItem label="Designation" value={profile.designation || 'Not provided'} />
                                            <DetailItem label="Institute" value={profile.institute?.name ?? 'Not linked'} />
                                            <DetailItem label="Joining date" value={profile.joining_date?.slice(0, 10) ?? 'Not provided'} />
                                            <DetailItem label="Leaving date" value={profile.leaving_date?.slice(0, 10) ?? 'Currently employed'} />
                                            <DetailItem label="Related expenses" value={String(profile.institute_expenses_count ?? 0)} />
                                        </dl>
                                        <div className="mt-5 border-t border-slate-100 pt-4">
                                            <p className="text-xs font-medium text-slate-500">
                                                Branch and department assignments
                                            </p>
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                {profile.stuff_assignments?.length ? (
                                                    profile.stuff_assignments.map((assignment) => (
                                                        <span
                                                            key={assignment.id}
                                                            className="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700"
                                                        >
                                                            {assignment.branch?.name ?? 'Unknown branch'}
                                                            <span className="mx-1.5 text-slate-400">/</span>
                                                            {assignment.department?.name ?? 'Unknown department'}
                                                        </span>
                                                    ))
                                                ) : (
                                                    <span className="text-sm text-slate-500">No branch assignment</span>
                                                )}
                                            </div>
                                        </div>
                                    </section>

                                    <section className="rounded-2xl border border-teal-200 bg-teal-50/70 p-5">
                                        <h3 className="text-sm font-semibold text-[#17375C]">
                                            National ID (NID)
                                        </h3>
                                        <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                                            <DetailItem label="NID number" value={profile.nid_number || 'Not provided'} />
                                            <div>
                                                <dt className="text-xs font-medium text-slate-500">NID document</dt>
                                                <dd className="mt-1">
                                                    {profile.nid_file_download_url ? (
                                                        <a
                                                            href={profile.nid_file_download_url}
                                                            download
                                                            className="inline-flex items-center rounded-lg bg-[#17375C] px-3 py-2 text-sm font-semibold text-white hover:bg-[#254878]"
                                                        >
                                                            Download NID document
                                                        </a>
                                                    ) : (
                                                        <span className="text-sm font-medium text-slate-600">
                                                            No NID document uploaded
                                                        </span>
                                                    )}
                                                </dd>
                                            </div>
                                        </dl>
                                    </section>

                                    <section className="rounded-2xl border border-slate-200 bg-white p-5">
                                        <h3 className="text-sm font-semibold text-[#17375C]">
                                            Connected records
                                        </h3>
                                        <dl className="mt-4 grid gap-x-6 gap-y-5 sm:grid-cols-2">
                                            <DetailItem label="User account" value={profile.user?.email ?? 'No linked account'} />
                                            <div>
                                                <dt className="text-xs font-medium text-slate-500">
                                                    Teacher profiles
                                                </dt>
                                                <dd className="mt-1 text-sm font-medium text-slate-800">
                                                    {profile.teachers?.length
                                                        ? profile.teachers.map((teacher) => (
                                                              <p key={teacher.id} className="mb-1">
                                                                  {teacher.full_name} ({teacher.teacher_code})
                                                              </p>
                                                          ))
                                                        : 'No linked teacher profile'}
                                                </dd>
                                            </div>
                                        </dl>
                                    </section>

                                    {profile.notes && (
                                        <section className="rounded-2xl border border-slate-200 bg-white p-5">
                                            <h3 className="text-sm font-semibold text-[#17375C]">
                                                Notes
                                            </h3>
                                            <p className="mt-2 whitespace-pre-wrap text-sm text-slate-700">
                                                {profile.notes}
                                            </p>
                                        </section>
                                    )}

                                    <div className="flex justify-end">
                                        <button
                                            type="button"
                                            onClick={() => setDialog(null)}
                                            className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm"
                                        >
                                            Close
                                        </button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}

                    {(dialog === 'edit' || dialog === 'create') && (
                        <form onSubmit={save} className="space-y-4">
                            {dialog === 'create' && (
                                <label className="block text-sm font-medium">
                                    Institute <span className="text-rose-600">*</span>
                                    <select
                                        required
                                        value={createInstituteId}
                                        onChange={(event) => {
                                            setCreateInstituteId(event.target.value);
                                            setCreateAssignments([]);
                                        }}
                                        className={field}
                                    >
                                        <option value="">Select an institute</option>
                                        {stuffOptions.map((institute) => (
                                            <option key={institute.id} value={institute.id}>{institute.name}</option>
                                        ))}
                                    </select>
                                    {optionsError && <span className="mt-1 block text-xs text-rose-700">Could not load institute options: {optionsError}</span>}
                                </label>
                            )}
                            <div className="grid gap-4 sm:grid-cols-2">
                                {(
                                    [
                                        ['stuff_code', 'Stuff code', 'text', true],
                                        ['full_name', 'Full name', 'text', true],
                                        ['phone', 'Phone', 'tel', true],
                                        ['email', 'Email', 'email', false],
                                        ['nid_number', 'NID number', 'text', false],
                                        ['date_of_birth', 'Date of birth', 'date', false],
                                        ['designation', 'Designation', 'text', true],
                                        ['joining_date', 'Joining date', 'date', true],
                                        ['leaving_date', 'Leaving date', 'date', false],
                                        ['address', 'Address', 'text', false],
                                        ['notes', 'Notes', 'text', false],
                                    ] as const
                                ).map(([name, label, type, required]) => (
                                    <label key={name} className="text-sm font-medium">
                                        {label}
                                        {required && ' *'}
                                        <input
                                            type={type}
                                            required={required}
                                            value={form[name]}
                                            onChange={(event) =>
                                                setForm({ ...form, [name]: event.target.value })
                                            }
                                            className={field}
                                        />
                                    </label>
                                ))}
                                <label className="text-sm font-medium">
                                    Employment status
                                    <select
                                        value={form.employment_status}
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                employment_status: event.target.value,
                                            })
                                        }
                                        className={field}
                                    >
                                        {statuses.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            </div>
                            {dialog === 'create' && (
                                <section className="space-y-3 rounded-xl border border-slate-200 p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <h3 className="text-sm font-semibold text-[#17375C]">Branch assignments</h3>
                                            <p className="mt-1 text-xs text-slate-500">Assignments are optional. Choose from the selected institute.</p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setCreateAssignments((rows) => [...rows, { branch_id: '', department_id: '' }])}
                                            disabled={!createInstitute}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-[#17375C] hover:bg-slate-50 disabled:opacity-50"
                                        >
                                            <Plus size={15} aria-hidden="true" /> Add assignment
                                        </button>
                                    </div>
                                    {createAssignments.map((assignment, index) => (
                                        <div key={index} className="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                                            <label className="text-sm font-medium">
                                                Branch
                                                <select
                                                    required
                                                    value={assignment.branch_id}
                                                    onChange={(event) => setCreateAssignments((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, branch_id: event.target.value } : row))}
                                                    className={field}
                                                >
                                                    <option value="">Select branch</option>
                                                    {createInstitute?.branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}
                                                </select>
                                            </label>
                                            <label className="text-sm font-medium">
                                                Department
                                                <select
                                                    required
                                                    value={assignment.department_id}
                                                    onChange={(event) => setCreateAssignments((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, department_id: event.target.value } : row))}
                                                    className={field}
                                                >
                                                    <option value="">Select department</option>
                                                    {createInstitute?.departments.map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}
                                                </select>
                                            </label>
                                            <button
                                                type="button"
                                                aria-label={`Remove assignment ${index + 1}`}
                                                title="Remove assignment"
                                                onClick={() => setCreateAssignments((rows) => rows.filter((_, rowIndex) => rowIndex !== index))}
                                                className="mt-2 inline-flex size-11 items-center justify-center self-end rounded-xl border border-rose-200 text-rose-700 hover:bg-rose-50"
                                            >
                                                <Trash2 size={16} aria-hidden="true" />
                                            </button>
                                        </div>
                                    ))}
                                </section>
                            )}
                            <section className="space-y-3 rounded-xl border border-slate-200 p-4">
                                <div className="flex items-center gap-4">
                                    {selectedStuff?.photoUrl ? (
                                        <img
                                            src={selectedStuff.photoUrl}
                                            alt={`${selectedStuff.name} profile`}
                                            className="size-14 rounded-xl object-cover"
                                        />
                                    ) : (
                                        <div className="flex size-14 items-center justify-center rounded-xl bg-slate-100 text-lg font-semibold text-slate-600">
                                            {selectedStuff ? stuffInitials(selectedStuff.name) : 'ST'}
                                        </div>
                                    )}
                                    <div>
                                        <h3 className="text-sm font-semibold text-[#17375C]">
                                            Profile photo
                                        </h3>
                                        <p className="mt-1 text-xs text-slate-500">
                                            JPG, PNG, or WebP, up to 3 MB.
                                        </p>
                                    </div>
                                </div>
                                <label className="block text-sm font-medium">
                                    {hasPhoto ? 'Replace profile photo' : 'Choose profile photo'}
                                    <input
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                photo: event.target.files?.[0] ?? null,
                                                remove_photo: false,
                                            })
                                        }
                                        className={`${field} h-auto py-2`}
                                    />
                                </label>
                                {hasPhoto && (
                                    <label className="flex items-center gap-2 text-sm text-slate-700">
                                        <input
                                            type="checkbox"
                                            checked={form.remove_photo}
                                            onChange={(event) =>
                                                setForm({
                                                    ...form,
                                                    remove_photo: event.target.checked,
                                                    photo: null,
                                                })
                                            }
                                            className="size-4 rounded border-slate-300"
                                        />
                                        Remove current profile photo
                                    </label>
                                )}
                            </section>
                            <section className="space-y-3 rounded-xl border border-slate-200 p-4">
                                <div>
                                    <h3 className="text-sm font-semibold text-[#17375C]">
                                        NID document
                                    </h3>
                                    <p className="mt-1 text-xs text-slate-500">
                                        Upload a PDF, JPG, or PNG file up to 5 MB.
                                    </p>
                                    <p className="mt-1 text-xs text-slate-600">
                                        {hasNidFile ? 'An NID document is on file.' : 'No NID document is on file.'}
                                    </p>
                                </div>
                                <label className="block text-sm font-medium">
                                    {hasNidFile ? 'Replace NID file' : 'Choose NID file'}
                                    <input
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                nid_file: event.target.files?.[0] ?? null,
                                                remove_nid_file: false,
                                            })
                                        }
                                        className={`${field} h-auto py-2`}
                                    />
                                </label>
                                {hasNidFile && (
                                    <label className="flex items-center gap-2 text-sm text-slate-700">
                                        <input
                                            type="checkbox"
                                            checked={form.remove_nid_file}
                                            onChange={(event) =>
                                                setForm({
                                                    ...form,
                                                    remove_nid_file: event.target.checked,
                                                    nid_file: null,
                                                })
                                            }
                                            className="size-4 rounded border-slate-300"
                                        />
                                        Remove current NID file
                                    </label>
                                )}
                            </section>
                            {formError && (
                                <p role="alert" className="text-sm text-rose-700">
                                    {formError}
                                </p>
                            )}
                            <div className="flex justify-end gap-3 border-t border-slate-100 pt-4">
                                <button
                                    type="button"
                                    onClick={() => setDialog(null)}
                                    disabled={saving}
                                    className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm"
                                >
                                    Cancel
                                </button>
                                <button type="submit" disabled={saving} className={primary}>
                                    {saving ? 'Saving…' : dialog === 'create' ? 'Add employee' : 'Save changes'}
                                </button>
                            </div>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
