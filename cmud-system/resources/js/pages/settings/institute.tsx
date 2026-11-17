import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Building2, LoaderCircle, Plus, Save, Trash2, Upload, X } from 'lucide-react';
import { useCallback, useEffect, useState, type ChangeEvent, type FormEvent } from 'react';

type InstituteOption = { id: number; code: string; name: string };
type Branch = { id?: number; code: string; name: string; phone: string; email: string | null; address: string; is_active: boolean };
type Institute = InstituteOption & { phone: string; email: string; tin_number: string | null; bin_number: string | null; website: string | null; address: string; logo_path: string | null; logo_url: string | null; is_active: boolean; branches: Branch[] };
type FormState = Omit<Institute, 'id' | 'logo_url' | 'branches'> & { branches: Branch[]; deleted_branch_ids: number[]; logo: File | null; remove_logo: boolean };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/institute' },
    { title: 'Institute', href: '/settings/institute' },
];

async function apiRequest<T>(url: string, init?: RequestInit): Promise<T> {
    const response = await fetch(url, { ...init, headers: { Accept: 'application/json', ...init?.headers }, credentials: 'same-origin' });
    const result = await response.json().catch(() => null) as (T & { message?: string; errors?: Record<string, string[]> }) | null;
    if (!response.ok) throw new Error(result?.errors ? Object.values(result.errors).flat()[0] : result?.message ?? `Request failed (${response.status}).`);
    return result as T;
}

function emptyForm(): FormState {
    return { code: '', name: '', phone: '', email: '', tin_number: '', bin_number: '', website: '', address: '', logo_path: null, is_active: true, branches: [], deleted_branch_ids: [], logo: null, remove_logo: false };
}

/** Manage the selected institute's profile, branding, and branches through the settings API. */
export default function InstituteSettings() {
    const [institutes, setInstitutes] = useState<InstituteOption[]>([]);
    const [instituteId, setInstituteId] = useState('');
    const [form, setForm] = useState<FormState>(emptyForm);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [logoUrl, setLogoUrl] = useState<string | null>(null);

    const loadInstitute = useCallback(async (id: string) => {
        setLoading(true);
        setError('');
        try {
            const response = await apiRequest<{ data: Institute }>(`/api/v1/settings/institutes/${id}`);
            const { logo_url, branches, ...institute } = response.data;
            setForm({ ...emptyForm(), ...institute, branches: branches ?? [], deleted_branch_ids: [], logo: null, remove_logo: false });
            setLogoUrl(logo_url);
        } catch (loadError) {
            setError(loadError instanceof Error ? loadError.message : 'Could not load institute settings.');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        let cancelled = false;
        async function loadInstitutes() {
            try {
                const response = await apiRequest<{ data: InstituteOption[] }>('/api/v1/settings/institutes');
                if (cancelled) return;
                setInstitutes(response.data);
                if (response.data.length) setInstituteId(String(response.data[0].id));
                else { setLoading(false); setError('No institute records were found.'); }
            } catch (loadError) {
                if (!cancelled) { setLoading(false); setError(loadError instanceof Error ? loadError.message : 'Could not load institutes.'); }
            }
        }
        void loadInstitutes();
        return () => { cancelled = true; };
    }, []);

    useEffect(() => { if (instituteId) void loadInstitute(instituteId); }, [instituteId, loadInstitute]);

    function updateField(name: keyof FormState, value: unknown) {
        setForm((current) => ({ ...current, [name]: value }));
        setNotice('');
    }

    function updateBranch(index: number, name: keyof Branch, value: string | boolean) {
        setForm((current) => ({ ...current, branches: current.branches.map((branch, itemIndex) => itemIndex === index ? { ...branch, [name]: value } : branch) }));
        setNotice('');
    }

    function addBranch() {
        setForm((current) => ({ ...current, branches: [...current.branches, { code: '', name: '', phone: '', email: '', address: '', is_active: true }] }));
    }

    function removeBranch(index: number) {
        setForm((current) => {
            const branch = current.branches[index];
            return { ...current, branches: current.branches.filter((_, itemIndex) => itemIndex !== index), deleted_branch_ids: branch?.id ? [...current.deleted_branch_ids, branch.id] : current.deleted_branch_ids };
        });
    }

    function pickLogo(event: ChangeEvent<HTMLInputElement>) {
        const logo = event.target.files?.[0] ?? null;
        updateField('logo', logo);
        if (logo) updateField('remove_logo', false);
    }

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!instituteId) return;
        setSaving(true);
        setError('');
        setNotice('');
        try {
            const payload = new FormData();
            for (const [key, value] of Object.entries(form)) {
                if (key === 'logo' || key === 'branches' || key === 'deleted_branch_ids') continue;
                if (value !== null && value !== undefined) payload.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
            }
            if (form.logo) payload.append('logo', form.logo);
            form.branches.forEach((branch, index) => Object.entries(branch).forEach(([key, value]) => {
                if (value !== undefined && value !== null) payload.append(`branches[${index}][${key}]`, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
            }));
            form.deleted_branch_ids.forEach((id, index) => payload.append(`deleted_branch_ids[${index}]`, String(id)));

            // Multipart updates are sent as POST with a method override for Laravel's request parser.
            const response = await apiRequest<{ data: Institute; message: string }>(`/api/v1/settings/institutes/${instituteId}`, { method: 'POST', headers: { 'X-HTTP-Method-Override': 'PUT' }, body: payload });
            const { logo_url, branches, ...institute } = response.data;
            setForm({ ...emptyForm(), ...institute, branches: branches ?? [], deleted_branch_ids: [], logo: null, remove_logo: false });
            setLogoUrl(logo_url);
            setNotice(response.message);
            const fileInput = document.getElementById('institute-logo') as HTMLInputElement | null;
            if (fileInput) fileInput.value = '';
        } catch (saveError) {
            setError(saveError instanceof Error ? saveError.message : 'Could not save institute settings.');
        } finally {
            setSaving(false);
        }
    }

    function field(name: keyof FormState, label: string, type = 'text', required = false) {
        const value = form[name];
        return <label key={name} className="block text-xs font-medium text-slate-600">{label}{required && <span className="ml-1 text-rose-500">*</span>}<input type={type} required={required} value={typeof value === 'string' ? value : ''} onChange={(event) => updateField(name, event.target.value)} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-[#16828D] focus:ring-3 focus:ring-[#16828D]/10" /></label>;
    }

    return <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Institute settings" />
        <main className="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-center gap-3"><span className="grid h-11 w-11 place-items-center rounded-xl bg-cyan-50 text-[#087586]"><Building2 size={21} /></span><div><p className="text-xs font-semibold tracking-wide text-[#087586] uppercase">Settings</p><h1 className="mt-1 text-2xl font-bold text-[#17375C]">Institute</h1></div></div>
                {institutes.length > 1 && <label className="block min-w-56 text-xs font-medium text-slate-600">Institute<select value={instituteId} onChange={(event) => setInstituteId(event.target.value)} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm"><option value="">Select institute</option>{institutes.map((institute) => <option key={institute.id} value={institute.id}>{institute.name}</option>)}</select></label>}
            </div>
            {error && <p role="alert" className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</p>}
            {notice && <p role="status" className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{notice}</p>}
            {loading ? <div className="flex items-center justify-center rounded-2xl border border-slate-200 bg-white py-20 text-sm text-slate-500"><LoaderCircle size={18} className="mr-2 animate-spin" />Loading institute settings…</div> : instituteId && <form onSubmit={submit} className="space-y-6">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 className="text-base font-semibold text-[#17375C]">Institute profile</h2><p className="mt-1 text-sm text-slate-500">Update organization details and branding.</p>
                    <div className="mt-5 grid gap-4 sm:grid-cols-2">{field('name', 'Institute name', 'text', true)}{field('code', 'Institute code', 'text', true)}{field('phone', 'Phone', 'tel', true)}{field('email', 'Email', 'email', true)}{field('tin_number', 'TIN number')}{field('bin_number', 'BIN number')}{field('website', 'Website', 'url')}<label className="block text-xs font-medium text-slate-600">Status<select value={form.is_active ? '1' : '0'} onChange={(event) => updateField('is_active', event.target.value === '1')} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm"><option value="1">Active</option><option value="0">Inactive</option></select></label>
                        <label className="block text-xs font-medium text-slate-600 sm:col-span-2">Address<textarea required value={form.address} onChange={(event) => updateField('address', event.target.value)} rows={3} className="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-[#16828D]" /></label>
                        <div className="sm:col-span-2"><p className="text-xs font-medium text-slate-600">Institute logo</p><div className="mt-2 flex flex-wrap items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4">{(form.logo || (logoUrl && !form.remove_logo)) && <img src={form.logo ? URL.createObjectURL(form.logo) : logoUrl ?? undefined} alt="Institute logo preview" className="h-16 w-16 rounded-lg border border-slate-200 bg-white object-contain p-1" />}<label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"><Upload size={14} />{form.logo ? form.logo.name : 'Choose logo'}<input id="institute-logo" type="file" accept="image/png,image/jpeg,image/webp" onChange={pickLogo} className="sr-only" /></label>{(logoUrl || form.logo) && <button type="button" onClick={() => { updateField('remove_logo', true); updateField('logo', null); setLogoUrl(null); }} className="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50"><X size={14} />Remove logo</button>}<span className="text-[11px] text-slate-500">PNG, JPG, or WebP up to 3 MB.</span></div></div>
                    </div>
                </section>
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-base font-semibold text-[#17375C]">Branches</h2><p className="mt-1 text-sm text-slate-500">Edit branch contact details or add a branch to this institute.</p></div><button type="button" onClick={addBranch} className="inline-flex items-center gap-2 rounded-lg border border-[#087586]/20 bg-cyan-50 px-3 py-2 text-xs font-semibold text-[#087586] hover:bg-cyan-100"><Plus size={15} />Add branch</button></div>
                    {form.branches.length ? <div className="mt-5 space-y-4">{form.branches.map((branch, index) => <div key={branch.id ?? `new-${index}`} className="rounded-xl border border-slate-200 bg-slate-50/70 p-4"><div className="mb-3 flex items-center justify-between"><h3 className="text-sm font-semibold text-slate-700">{branch.name || `Branch ${index + 1}`}</h3><button type="button" onClick={() => removeBranch(index)} aria-label={`Remove ${branch.name || `branch ${index + 1}`}`} className="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600"><Trash2 size={15} /></button></div><div className="grid gap-3 sm:grid-cols-2">{(['name', 'code', 'phone', 'email'] as const).map((name) => <label key={name} className="block text-xs font-medium capitalize text-slate-600">{name}<input type={name === 'email' ? 'email' : 'text'} required={name !== 'email'} value={branch[name] ?? ''} onChange={(event) => updateBranch(index, name, event.target.value)} className="mt-1.5 h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm" /></label>)}<label className="block text-xs font-medium text-slate-600 sm:col-span-2">Address<textarea required value={branch.address} onChange={(event) => updateBranch(index, 'address', event.target.value)} rows={2} className="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" /></label><label className="inline-flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" checked={branch.is_active} onChange={(event) => updateBranch(index, 'is_active', event.target.checked)} className="rounded border-slate-300 text-[#087586]" />Active</label></div></div>)}</div> : <p className="mt-5 rounded-xl border border-dashed border-slate-200 py-8 text-center text-sm text-slate-500">No branches are registered for this institute.</p>}
                </section>
                <div className="flex justify-end"><button type="submit" disabled={saving} className="inline-flex items-center gap-2 rounded-lg bg-[#17375C] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#254878] disabled:opacity-60">{saving ? <LoaderCircle size={16} className="animate-spin" /> : <Save size={16} />}{saving ? 'Saving…' : 'Save institute settings'}</button></div>
            </form>}
        </main>
    </AppLayout>;
}
