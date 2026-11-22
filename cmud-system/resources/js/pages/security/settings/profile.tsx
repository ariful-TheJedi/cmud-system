import { Form, Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

export default function Profile({ mustVerifyEmail, status }: { mustVerifyEmail: boolean; status?: string }) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const initials = useInitials();
    const field = 'mt-2 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/10';
    return <AppLayout breadcrumbs={[{ title: 'Settings', href: '/settings/institute' }, { title: 'Profile', href: '/settings/profile' }]}>
        <Head title="Profile" />
        <main className="min-h-screen bg-[#F7F9FC] px-4 py-8 text-slate-800 sm:px-7 lg:px-10"><div className="mx-auto max-w-5xl space-y-6">
            <header className="rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-7 text-white sm:p-9"><p className="text-xs font-semibold tracking-widest text-cyan-100 uppercase">Settings</p><h1 className="mt-4 text-3xl font-semibold">Profile</h1><p className="mt-2 text-sm text-cyan-50">Update your photo and personal information.</p></header>
            <Form action="/settings/profile" method="post" encType="multipart/form-data" options={{ preserveScroll: true }} className="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
                {({ processing, errors, recentlySuccessful }) => <>
                    <input type="hidden" name="_method" value="patch" />
                    <div className="flex items-center gap-4"><Avatar className="size-20"><AvatarImage src={auth.user.avatar} alt={auth.user.name} /><AvatarFallback>{initials(auth.user.name)}</AvatarFallback></Avatar><div><p className="font-semibold text-[#17375C]">{auth.user.name}</p><p className="text-sm text-slate-500">{auth.user.email}</p></div></div>
                    <label className="block text-sm font-medium">Profile photo<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" className="mt-2 block w-full rounded-xl border border-slate-200 p-3 text-sm" /><span className="mt-2 block text-xs text-slate-500">JPG, PNG or WebP, up to 2 MB. Leave empty to keep your current photo.</span><InputError message={errors.photo} /></label>
                    <div className="grid gap-6 sm:grid-cols-2">
                        <label className="text-sm font-medium">Full name<input name="name" defaultValue={auth.user.name} required autoComplete="name" className={field} /><InputError message={errors.name} /></label>
                        <label className="text-sm font-medium">Email address<input name="email" type="email" defaultValue={auth.user.email} required autoComplete="email" className={field} /><InputError message={errors.email} /></label>
                        <label className="text-sm font-medium">Phone number<input name="phone" type="tel" defaultValue={typeof auth.user.phone === 'string' ? auth.user.phone : ''} maxLength={30} autoComplete="tel" className={field} /><InputError message={errors.phone} /></label>
                    </div>
                    {mustVerifyEmail && !auth.user.email_verified_at && <div className="text-sm text-slate-600">Your email address is unverified. <Link href={send()} as="button" className="font-medium text-teal-700 underline">Resend verification email</Link>{status === 'verification-link-sent' && <p role="status" className="mt-2 text-emerald-700">A new verification link has been sent.</p>}</div>}
                    <div className="flex items-center gap-4 border-t border-slate-100 pt-5"><button type="submit" disabled={processing} data-test="update-profile-button" className="rounded-xl bg-[#17375C] px-5 py-3 text-sm font-semibold text-white hover:bg-[#254878] disabled:opacity-50">{processing ? 'Saving?' : 'Save changes'}</button>{recentlySuccessful && <p role="status" className="text-sm text-emerald-700">Profile updated.</p>}</div>
                </>}
            </Form>
        </div></main>
    </AppLayout>;
}
