import { useState, type FormEvent } from 'react';

type Account = { id: number; name: string; type: string; provider: string; holder: string; number: string; balance: number };
type Transfer = { id: number; from: string; to: string; amount: number; date: string; reference: string };
const money = (amount: number) => `৳ ${(amount / 100).toLocaleString('en-BD', { minimumFractionDigits: 2 })}`;
const field = 'mt-2 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm focus:outline-teal-600';
const button = 'rounded-xl bg-[#17375C] px-4 py-3 text-sm font-semibold text-white hover:bg-[#254878]';

export default function InstituteAccounts({ instituteName }: { instituteName: string }) {
    const [accounts, setAccounts] = useState<Account[]>([
        { id: 1, name: 'Main bank account', type: 'Bank', provider: 'Demo Bank', holder: instituteName, number: 'DEMO-001', balance: 5000000 },
        { id: 2, name: 'Mobile collection', type: 'Mobile payment', provider: 'bKash', holder: instituteName, number: 'DEMO-002', balance: 1000000 },
    ]);
    const [transfers, setTransfers] = useState<Transfer[]>([]);
    const [tab, setTab] = useState<'accounts' | 'transfers'>('accounts');
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [account, setAccount] = useState({ name: '', type: 'Bank', provider: '', holder: instituteName, number: '', opening: '' });
    const [transfer, setTransfer] = useState({ from: '', to: '', amount: '', date: '', reference: '' });

    function addAccount(event: FormEvent) {
        event.preventDefault();
        const balance = Math.round(Number(account.opening || 0) * 100);
        if (!account.name.trim() || !account.holder.trim() || (account.type !== 'Cash' && (!account.provider.trim() || !account.number.trim())) || !Number.isSafeInteger(balance) || balance < 0) { setError('Complete account details and enter a valid opening balance.'); return; }
        if (account.number.trim() && accounts.some((item) => item.provider.toLowerCase() === account.provider.trim().toLowerCase() && item.number === account.number.trim())) { setError('This account is already listed.'); return; }
        setAccounts((current) => [...current, { id: Math.max(...current.map((item) => item.id), 0) + 1, name: account.name.trim(), type: account.type, provider: account.provider.trim(), holder: account.holder.trim(), number: account.number.trim(), balance }]);
        setAccount({ name: '', type: 'Bank', provider: '', holder: instituteName, number: '', opening: '' });
        setError(''); setNotice('Demo account added.');
    }

    function addTransfer(event: FormEvent) {
        event.preventDefault();
        const from = accounts.find((item) => item.id === Number(transfer.from));
        const to = accounts.find((item) => item.id === Number(transfer.to));
        const amount = Math.round(Number(transfer.amount) * 100);
        if (!from || !to || from.id === to.id || !transfer.date || !Number.isSafeInteger(amount) || amount <= 0) { setError('Choose different source and destination accounts, a date, and a valid positive amount.'); return; }
        if (amount > from.balance) { setError('The source account has insufficient balance.'); return; }
        // A transfer moves funds between accounts and does not create revenue or expense.
        setAccounts((current) => current.map((item) => ({ ...item, balance: item.balance + (item.id === from.id ? -amount : item.id === to.id ? amount : 0) })));
        setTransfers((current) => [...current, { id: current.length + 1, from: from.name, to: to.name, amount, date: transfer.date, reference: transfer.reference.trim() }]);
        setTransfer({ from: '', to: '', amount: '', date: '', reference: '' });
        setError(''); setNotice('Demo transfer recorded.');
    }

    return <section aria-labelledby="institute-accounts" className="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
        <div><h2 id="institute-accounts" className="text-xl font-semibold text-[#17375C]">Accounts & transfers</h2><p className="mt-2 text-sm text-slate-500">Manage bank, mobile payment, and cash accounts for {instituteName}.</p><p className="mt-2 text-sm text-amber-700">Demo only. Sample balances and changes reset when you leave or refresh. No money is transferred.</p></div>
        <div className="flex gap-2" aria-label="Account management">{(['accounts', 'transfers'] as const).map((value) => <button key={value} type="button" aria-pressed={tab === value} onClick={() => { setTab(value); setError(''); setNotice(''); }} className={`rounded-xl px-4 py-2 text-sm font-semibold ${tab === value ? 'bg-[#17375C] text-white' : 'bg-slate-100 text-slate-600'}`}>{value === 'accounts' ? 'Accounts' : 'Account transfers'}</button>)}</div>
        {error && <p role="alert" className="text-sm text-rose-700">{error}</p>}{notice && <p role="status" className="text-sm text-emerald-700">{notice}</p>}
        {tab === 'accounts' ? <>
            <div className="grid gap-4 sm:grid-cols-2">{accounts.map((item) => <article key={item.id} className="rounded-xl border border-slate-200 p-4"><h3 className="font-semibold text-[#17375C]">{item.name}</h3><p className="mt-1 text-sm text-slate-500">{item.type}{item.provider && ` · ${item.provider}`}</p><p className="mt-2 text-sm">{item.holder}{item.number && ` · ${item.number}`}</p><p className="mt-3 text-lg font-semibold">{money(item.balance)}</p><p className="text-xs text-slate-500">Demo balance</p></article>)}</div>
            <form onSubmit={addAccount} className="space-y-4 border-t border-slate-100 pt-5"><h3 className="font-semibold text-[#17375C]">Add account</h3><div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label className="text-sm font-medium">Account type<select value={account.type} onChange={(event) => setAccount({ ...account, type: event.target.value, provider: '', number: '' })} className={field}>{['Bank', 'Mobile payment', 'Cash', 'Other'].map((type) => <option key={type}>{type}</option>)}</select></label>
                <label className="text-sm font-medium">Account name<input required value={account.name} onChange={(event) => setAccount({ ...account, name: event.target.value })} className={field} /></label>
                <label className="text-sm font-medium">Account holder<input required value={account.holder} onChange={(event) => setAccount({ ...account, holder: event.target.value })} className={field} /></label>
                {account.type !== 'Cash' && <><label className="text-sm font-medium">Bank / provider<input required placeholder="Bank name, bKash, Nagad…" value={account.provider} onChange={(event) => setAccount({ ...account, provider: event.target.value })} className={field} /></label><label className="text-sm font-medium">Account / mobile number<input required value={account.number} onChange={(event) => setAccount({ ...account, number: event.target.value })} className={field} /></label></>}
                <label className="text-sm font-medium">Opening balance (BDT)<input type="number" min="0" step="0.01" value={account.opening} onChange={(event) => setAccount({ ...account, opening: event.target.value })} className={field} /></label>
            </div><button type="submit" className={button}>Add demo account</button></form>
        </> : <>
            <form onSubmit={addTransfer} className="space-y-4"><h3 className="font-semibold text-[#17375C]">Transfer between accounts</h3><p className="text-sm text-slate-500">Internal transfers move balances between accounts. They do not count as income or expenditure.</p><div className="grid gap-4 sm:grid-cols-2">
                <label className="text-sm font-medium">From account<select required value={transfer.from} onChange={(event) => setTransfer({ ...transfer, from: event.target.value, to: event.target.value === transfer.to ? '' : transfer.to })} className={field}><option value="">Choose source</option>{accounts.map((item) => <option key={item.id} value={item.id}>{item.name} · {money(item.balance)}</option>)}</select></label>
                <label className="text-sm font-medium">To account<select required value={transfer.to} onChange={(event) => setTransfer({ ...transfer, to: event.target.value })} className={field}><option value="">Choose destination</option>{accounts.filter((item) => String(item.id) !== transfer.from).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                <label className="text-sm font-medium">Amount (BDT)<input required type="number" min="0.01" step="0.01" value={transfer.amount} onChange={(event) => setTransfer({ ...transfer, amount: event.target.value })} className={field} /></label>
                <label className="text-sm font-medium">Transfer date<input required type="date" value={transfer.date} onChange={(event) => setTransfer({ ...transfer, date: event.target.value })} className={field} /></label>
                <label className="text-sm font-medium">Reference (optional)<input value={transfer.reference} onChange={(event) => setTransfer({ ...transfer, reference: event.target.value })} className={field} /></label>
            </div><button type="submit" className={button}>Record demo transfer</button></form>
            <div className="overflow-x-auto border-t border-slate-100 pt-5"><h3 className="mb-3 font-semibold text-[#17375C]">Transfer history</h3><table className="w-full text-left text-sm"><thead><tr>{['Date', 'From', 'To', 'Amount', 'Reference'].map((label) => <th key={label} className="px-3 py-2 font-medium text-slate-500">{label}</th>)}</tr></thead><tbody>{transfers.map((item) => <tr key={item.id} className="border-t border-slate-100"><td className="whitespace-nowrap px-3 py-3">{item.date}</td><td className="px-3 py-3">{item.from}</td><td className="px-3 py-3">{item.to}</td><td className="whitespace-nowrap px-3 py-3">{money(item.amount)}</td><td className="px-3 py-3">{item.reference || '—'}</td></tr>)}{!transfers.length && <tr><td colSpan={5} className="px-3 py-6 text-center text-slate-500">No demo transfers recorded.</td></tr>}</tbody></table></div>
        </>}
    </section>;
}
