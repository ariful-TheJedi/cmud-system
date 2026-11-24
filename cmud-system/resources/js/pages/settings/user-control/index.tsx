import { Head } from '@inertiajs/react';
import { KeyRound, LoaderCircle, Pencil, Plus, UserRound, Users } from 'lucide-react';
import { useCallback, useEffect, useState, type FormEvent } from 'react';
import AppLayout from '@/layouts/app-layout';
import { ApiRequestError, useApi } from '@/hooks/use-api';
import type { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Employee = {
    id: number;
    full_name: string;
    email: string;
    phone: string | null;
    designation: string | null;
    stuff_code: string;
    branch_id: number | null;
    branch_name: string | null;
    branch_assignment_count: number;
    branch_is_active: boolean;
};
type Branch = { id: number; name: string; code: string; institute_id: number };
type Account = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    designation: string | null;
    is_active: boolean;
    is_super_admin: boolean;
    branch_id: number | null;
    branch: Branch | null;
};
type Envelope<T> = { data: T; message?: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/institute' },
    { title: 'User Control', href: '/settings/user-control' },
];

function describeError(error: unknown): string {
    if (error instanceof ApiRequestError) {
        return error.errors ? Object.values(error.errors).flat()[0] ?? error.message : error.message;
    }

    return error instanceof Error ? error.message : 'The request failed.';
}

/** Manage login accounts and create them from existing employee records. */
export default function UserControl() {
    const { request } = useApi();
    const [accounts, setAccounts] = useState<Account[]>([]);
    const [employees, setEmployees] = useState<Employee[]>([]);
    const [branches, setBranches] = useState<Branch[]>([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingAccount, setEditingAccount] = useState<Account | null>(null);
    const [editValues, setEditValues] = useState({ name: '', email: '', phone: '', designation: '', branchId: '', isActive: true, password: '', passwordConfirmation: '' });
    const [stuffId, setStuffId] = useState('');
    const [creationMode, setCreationMode] = useState<'employee' | 'standalone'>('employee');
    const [branchId, setBranchId] = useState('');
    const [newUser, setNewUser] = useState({ name: '', email: '', phone: '', designation: '' });
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [isActive, setIsActive] = useState(true);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');

    const loadData = useCallback(async (signal?: AbortSignal) => {
        setLoading(true);
        setError('');

        try {
            const [accountResponse, employeeResponse, branchResponse] = await Promise.all([
                request<Envelope<Account[]>>('/users', { signal }),
                request<Envelope<Employee[]>>('/users/eligible-employees', { signal }),
                request<Envelope<Branch[]>>('/users/branches', { signal }),
            ]);
            setAccounts(accountResponse.data.data);
            setEmployees(employeeResponse.data.data);
            setBranches(branchResponse.data.data);
        } catch (loadError) {
            if (!signal?.aborted) setError(describeError(loadError));
        } finally {
            if (!signal?.aborted) setLoading(false);
        }
    }, [request]);

    useEffect(() => {
        const controller = new AbortController();
        void loadData(controller.signal);
        return () => controller.abort();
    }, [loadData]);

    function openCreateDialog() {
        setStuffId('');
        setCreationMode('employee');
        setBranchId('');
        setNewUser({ name: '', email: '', phone: '', designation: '' });
        setPassword('');
        setPasswordConfirmation('');
        setIsActive(true);
        setError('');
        setNotice('');
        setDialogOpen(true);
    }

    async function createAccount(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setError('');

        if (password !== passwordConfirmation) {
            setError('Password confirmation does not match.');
            return;
        }

        setSaving(true);
        try {
            const response = await request<Envelope<Account>>(creationMode === 'employee' ? '/users/from-employee' : '/users', {
                method: 'POST',
                data: creationMode === 'employee'
                    ? { stuff_id: Number(stuffId), password, password_confirmation: passwordConfirmation, is_active: isActive }
                    : { ...newUser, branch_id: Number(branchId), password, password_confirmation: passwordConfirmation, is_active: isActive },
            });
            setAccounts((current) => [...current, response.data.data].sort((a, b) => a.name.localeCompare(b.name)));
            if (creationMode === 'employee') setEmployees((current) => current.filter((employee) => employee.id !== Number(stuffId)));
            setDialogOpen(false);
            setNotice(response.data.message ?? 'User account created.');
        } catch (saveError) {
            setError(describeError(saveError));
        } finally {
            setSaving(false);
        }
    }

    function openEditDialog(account: Account) {
        setError('');
        setEditValues({
            name: account.name,
            email: account.email,
            phone: account.phone ?? '',
            designation: account.designation ?? '',
            branchId: account.branch_id ? String(account.branch_id) : '',
            isActive: account.is_active,
            password: '',
            passwordConfirmation: '',
        });
        setEditingAccount(account);
    }

    async function updateAccount(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!editingAccount) return;
        setError('');

        if (editValues.password && editValues.password !== editValues.passwordConfirmation) {
            setError('Password confirmation does not match.');
            return;
        }

        setSaving(true);
        try {
            const { data } = await request<Envelope<Account>>(`/users/${editingAccount.id}`, {
                method: 'PUT',
                data: {
                    name: editValues.name,
                    email: editValues.email,
                    phone: editValues.phone || null,
                    designation: editValues.designation || null,
                    branch_id: editingAccount.is_super_admin ? null : Number(editValues.branchId),
                    is_active: editValues.isActive,
                    ...(editValues.password ? { password: editValues.password, password_confirmation: editValues.passwordConfirmation } : {}),
                },
            });
            setAccounts((current) => current.map((account) => account.id === data.data.id ? data.data : account));
            setEditingAccount(null);
            setNotice(data.message ?? 'User account updated.');
        } catch (saveError) {
            setError(describeError(saveError));
        } finally {
            setSaving(false);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Control | CMUD" />
            <main className="min-h-screen bg-[#F7F9FC] px-4 py-8 text-slate-800 sm:px-7 lg:px-10">
                <div className="mx-auto max-w-5xl space-y-6">
                    <header className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-7">
                        <div>
                            <div className="flex items-center gap-2 text-sm font-medium text-teal-700"><Users size={17} /> Security</div>
                            <h1 className="mt-2 text-2xl font-semibold tracking-tight text-[#17375C]">User Control</h1>
                            <p className="mt-1 text-sm text-slate-500">Create sign-in accounts from existing employee records.</p>
                        </div>
                        <Button onClick={openCreateDialog} disabled={branches.length === 0 && employees.length === 0} className="bg-[#17375C] hover:bg-[#254878]"><Plus size={16} /> Add user</Button>
                    </header>

                    {notice && <div role="status" className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{notice}</div>}
                    {error && !dialogOpen && !editingAccount && <div role="alert" className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">Could not load user accounts: {error}</div>}

                    <section className="grid gap-4 sm:grid-cols-2" aria-label="User account totals">
                        <article className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><p className="text-sm text-slate-500">User accounts</p><p className="mt-2 text-3xl font-semibold text-[#17375C]">{loading ? '—' : accounts.length}</p></article>
                        <article className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><p className="text-sm text-slate-500">Employees eligible for an account</p><p className="mt-2 text-3xl font-semibold text-[#17375C]">{loading ? '—' : employees.length}</p></article>
                    </section>

                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="users-heading">
                        <header className="border-b border-slate-100 px-5 py-4"><h2 id="users-heading" className="font-semibold text-[#17375C]">User accounts</h2><p className="mt-1 text-xs text-slate-500">Login accounts currently registered in the system</p></header>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[760px] text-left">
                                <thead className="bg-slate-50 text-xs font-semibold text-slate-500"><tr><th className="px-5 py-3">User</th><th className="px-5 py-3">Branch</th><th className="px-5 py-3">Access</th><th className="px-5 py-3">Designation</th><th className="px-5 py-3">Status</th><th className="px-5 py-3">Action</th></tr></thead>
                                <tbody className="divide-y divide-slate-100">
                                    {accounts.map((account) => <tr key={account.id}><td className="px-5 py-4"><div className="flex items-center gap-3"><span className="grid size-9 place-items-center rounded-full bg-teal-50 text-teal-800"><UserRound size={17} /></span><div><p className="text-sm font-medium text-slate-800">{account.name}</p><p className="mt-0.5 text-xs text-slate-500">{account.email}</p></div></div></td><td className="px-5 py-4 text-sm text-slate-600">{account.branch?.name ?? (account.is_super_admin ? 'All branches' : 'Unassigned')}</td><td className="px-5 py-4 text-sm text-slate-600">{account.is_super_admin ? 'Super user · all branches' : 'Single branch'}</td><td className="px-5 py-4 text-sm text-slate-600">{account.designation || '—'}</td><td className="px-5 py-4"><span className={`rounded-full px-2.5 py-1 text-xs font-medium ${account.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>{account.is_active ? 'Active' : 'Inactive'}</span></td><td className="px-5 py-4"><Button type="button" variant="outline" size="sm" onClick={() => openEditDialog(account)} aria-label={`Edit ${account.name}`}><Pencil size={14} /> Edit</Button></td></tr>)}
                                    {!loading && accounts.length === 0 && <tr><td colSpan={6} className="px-5 py-12 text-center text-sm text-slate-500">No user accounts found.</td></tr>}
                                    {loading && <tr><td colSpan={6} className="px-5 py-12 text-center text-sm text-slate-500"><LoaderCircle className="mr-2 inline size-4 animate-spin" />Loading user accounts…</td></tr>}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </main>

            {editingAccount && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget && !saving) setEditingAccount(null); }}>
                <section role="dialog" aria-modal="true" aria-labelledby="edit-user-title" className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-slate-200 bg-white p-6 shadow-xl">
                    <header className="mb-5"><h2 id="edit-user-title" className="text-lg font-semibold text-[#17375C]">Update user account</h2><p className="mt-1 text-sm text-slate-500">Update the account details and branch access.</p></header>
                    {error && <div role="alert" className="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{error}</div>}
                    <form onSubmit={updateAccount} className="space-y-4">
                        <div className="grid gap-2"><Label htmlFor="edit-user-name">Full name</Label><Input id="edit-user-name" required maxLength={255} value={editValues.name} onChange={(event) => setEditValues({ ...editValues, name: event.target.value })} /></div>
                        <div className="grid gap-2"><Label htmlFor="edit-user-email">Email</Label><Input id="edit-user-email" type="email" required maxLength={255} value={editValues.email} onChange={(event) => setEditValues({ ...editValues, email: event.target.value })} /></div>
                        <div className="grid gap-2"><Label htmlFor="edit-user-phone">Phone (optional)</Label><Input id="edit-user-phone" maxLength={30} value={editValues.phone} onChange={(event) => setEditValues({ ...editValues, phone: event.target.value })} /></div>
                        <div className="grid gap-2"><Label htmlFor="edit-user-designation">Designation (optional)</Label><Input id="edit-user-designation" maxLength={100} value={editValues.designation} onChange={(event) => setEditValues({ ...editValues, designation: event.target.value })} /></div>
                        {editingAccount.is_super_admin ? <div className="rounded-lg border border-teal-200 bg-teal-50 px-3 py-2 text-sm text-teal-900">Super user · access to all branches</div> : <div className="grid gap-2"><Label htmlFor="edit-user-branch">Assigned branch</Label><select id="edit-user-branch" required value={editValues.branchId} onChange={(event) => setEditValues({ ...editValues, branchId: event.target.value })} className="h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm focus:border-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700/20"><option value="">Select a branch</option>{branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.name} · {branch.code}</option>)}</select></div>}
                        <div className="grid gap-2"><Label htmlFor="edit-user-status">Account status</Label><select id="edit-user-status" value={editValues.isActive ? 'active' : 'inactive'} onChange={(event) => setEditValues({ ...editValues, isActive: event.target.value === 'active' })} className="h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm focus:border-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700/20"><option value="active">Active — can sign in</option><option value="inactive">Inactive — sign-in disabled</option></select></div>
                        <div className="border-t border-slate-100 pt-4"><p className="mb-3 text-sm font-medium text-slate-700">Change password <span className="font-normal text-slate-500">(optional)</span></p><div className="space-y-3"><div className="grid gap-2"><Label htmlFor="edit-user-password">New password</Label><Input id="edit-user-password" type="password" minLength={8} autoComplete="new-password" value={editValues.password} onChange={(event) => setEditValues({ ...editValues, password: event.target.value })} placeholder="Leave blank to keep current password" /></div><div className="grid gap-2"><Label htmlFor="edit-user-password-confirmation">Confirm new password</Label><Input id="edit-user-password-confirmation" type="password" minLength={8} autoComplete="new-password" value={editValues.passwordConfirmation} onChange={(event) => setEditValues({ ...editValues, passwordConfirmation: event.target.value })} /></div></div></div>
                        <footer className="flex justify-end gap-2 pt-2"><Button type="button" variant="outline" onClick={() => setEditingAccount(null)} disabled={saving}>Cancel</Button><Button type="submit" disabled={saving || !editValues.name || !editValues.email || (!editingAccount.is_super_admin && !editValues.branchId)} className="bg-[#17375C] hover:bg-[#254878]">{saving && <LoaderCircle className="size-4 animate-spin" />}Save changes</Button></footer>
                    </form>
                </section>
            </div>}

            {dialogOpen && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setDialogOpen(false); }}>
                <section role="dialog" aria-modal="true" aria-labelledby="create-user-title" className="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-6 shadow-xl">
                    <header className="mb-5"><h2 id="create-user-title" className="text-lg font-semibold text-[#17375C]">Create user account</h2><p className="mt-1 text-sm text-slate-500">Create a branch-scoped account from an employee record or enter new user details.</p></header>
                    <div className="mb-4 grid gap-2"><Label htmlFor="creation-mode">Create user</Label><select id="creation-mode" value={creationMode} onChange={(event) => { setCreationMode(event.target.value as 'employee' | 'standalone'); setError(''); }} className="h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm focus:border-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700/20"><option value="employee">From employee</option><option value="standalone">New user</option></select></div>
                    {error && <div role="alert" className="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{error}</div>}
                    <form onSubmit={createAccount} className="space-y-4">
                        {creationMode === 'employee' ? <><div className="grid min-w-0 gap-2"><Label htmlFor="employee">Employee</Label><select id="employee" required value={stuffId} onChange={(event) => setStuffId(event.target.value)} className="h-11 w-full min-w-0 max-w-full truncate rounded-lg border border-slate-300 bg-white px-3 pr-8 text-sm focus:border-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700/20"><option value="">Select an employee</option>{employees.map((employee) => <option key={employee.id} value={employee.id} disabled={employee.branch_assignment_count !== 1 || !employee.branch_is_active}>{employee.full_name} · {employee.stuff_code}</option>)}</select></div><p className="text-xs text-slate-500">The account inherits the employee’s only active branch assignment. Employees with zero or multiple branches must be corrected in the employee record first.</p></> : <><div className="grid gap-2"><Label htmlFor="new-name">Full name</Label><Input id="new-name" required maxLength={255} value={newUser.name} onChange={(event) => setNewUser({ ...newUser, name: event.target.value })} /></div><div className="grid gap-2"><Label htmlFor="new-email">Email</Label><Input id="new-email" type="email" required maxLength={255} value={newUser.email} onChange={(event) => setNewUser({ ...newUser, email: event.target.value })} /></div><div className="grid gap-2"><Label htmlFor="new-phone">Phone (optional)</Label><Input id="new-phone" maxLength={30} value={newUser.phone} onChange={(event) => setNewUser({ ...newUser, phone: event.target.value })} /></div><div className="grid gap-2"><Label htmlFor="new-designation">Designation (optional)</Label><Input id="new-designation" maxLength={100} value={newUser.designation} onChange={(event) => setNewUser({ ...newUser, designation: event.target.value })} /></div><div className="grid gap-2"><Label htmlFor="user-branch">Assigned branch</Label><select id="user-branch" required value={branchId} onChange={(event) => setBranchId(event.target.value)} className="h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm focus:border-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700/20"><option value="">Select a branch</option>{branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.name} · {branch.code}</option>)}</select></div></>}
                        <div className="grid gap-2"><Label htmlFor="new-password">Initial password</Label><Input id="new-password" type="password" required minLength={8} autoComplete="new-password" value={password} onChange={(event) => setPassword(event.target.value)} placeholder="At least 8 characters" /></div>
                        <div className="grid gap-2"><Label htmlFor="password-confirmation">Confirm password</Label><Input id="password-confirmation" type="password" required minLength={8} autoComplete="new-password" value={passwordConfirmation} onChange={(event) => setPasswordConfirmation(event.target.value)} placeholder="Enter the password again" /></div>
                        <div className="grid gap-2"><Label htmlFor="account-status">Account status</Label><select id="account-status" value={isActive ? 'active' : 'inactive'} onChange={(event) => setIsActive(event.target.value === 'active')} className="h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm focus:border-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700/20"><option value="active">Active — can sign in</option><option value="inactive">Inactive — sign-in disabled</option></select></div>
                        <p className="flex items-center gap-2 text-xs text-slate-500"><KeyRound size={14} /> Roles and permissions can be assigned later.</p>
                        <footer className="flex justify-end gap-2 pt-2"><Button type="button" variant="outline" onClick={() => setDialogOpen(false)} disabled={saving}>Cancel</Button><Button type="submit" disabled={saving || (creationMode === 'employee' ? !stuffId : !branchId || !newUser.name || !newUser.email)} className="bg-[#17375C] hover:bg-[#254878]">{saving && <LoaderCircle className="size-4 animate-spin" />}Create account</Button></footer>
                    </form>
                </section>
            </div>}
        </AppLayout>
    );
}
