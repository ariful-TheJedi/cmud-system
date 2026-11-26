type BillItem = {
    description: string;
    quantity: number;
    unit_price: string;
    line_total: string;
};

export type PrintableInstituteOtherBillRecord = {
    bill_number: string;
    university_name: string;
    bill_items: BillItem[];
    total_amount: string;
    bill_date: string;
    due_on: string | null;
    status: string;
    notes: string | null;
    issuer_name: string | null;
    recorded_by: string | null;
};

export type InstituteOtherBillPrintHeader = {
    name: string;
    logo_url: string;
    phone: string | null;
    email: string | null;
    address: string | null;
};

function formatMoney(amount: string | number): string {
    return new Intl.NumberFormat('en-BD', {
        style: 'currency',
        currency: 'BDT',
    }).format(Number(amount) || 0);
}

/** A separate, data-driven print view for Institute Other Bills. */
export default function PrintableInstituteOtherBill({
    bill,
    header,
}: {
    bill: PrintableInstituteOtherBillRecord;
    header?: InstituteOtherBillPrintHeader | null;
}) {
    // Keep the print component render-safe while header options are loading or absent.
    const invoiceHeader = header ?? {
        name: 'CMUD',
        logo_url: '/apple-touch-icon.png',
        phone: null,
        email: null,
        address: null,
    };
    const issuer = bill.issuer_name || bill.recorded_by || 'Institute';

    return (
        <>
            <style>{`
                * { box-sizing: border-box; }
                body { margin: 0; color: #172033; font: 13px Arial, sans-serif; }
                #institute-other-bill-print-root { display: none; }
                .paper { max-width: 850px; margin: 0 auto; padding: 42px; }
                .header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid #17375c; padding-bottom: 18px; }
                .identity { min-width: 0; }
                .logo { display: block; max-width: 180px; height: 54px; object-fit: contain; object-position: left; }
                .contact { margin-top: 5px; color: #64748b; font-size: 10px; line-height: 1.45; }
                .contact p { margin: 2px 0; overflow-wrap: anywhere; }
                .title { margin-left: auto; }
                .invoice-id { margin: 0; color: #17375c; font-size: 14px; font-weight: bold; text-align: right; }
                .summary { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin: 25px 0; }
                .label { color: #64748b; font-size: 10px; letter-spacing: .1em; text-transform: uppercase; }
                .value { margin-top: 5px; font-size: 14px; font-weight: bold; }
                .items { width: 100%; margin-top: 22px; border-collapse: collapse; }
                .items thead { display: table-header-group; }
                .items th, .items td { padding: 10px; border-bottom: 1px solid #dbe2ea; text-align: left; }
                .items th { background: #f1f5f9; color: #475569; font-size: 10px; text-transform: uppercase; }
                .items tr { break-inside: avoid; page-break-inside: avoid; }
                .num { text-align: right !important; white-space: nowrap; }
                .total { display: flex; justify-content: flex-end; margin-top: 18px; break-inside: avoid; }
                .total div { display: flex; min-width: 245px; justify-content: space-between; padding: 14px; background: #f1f5f9; font-size: 16px; font-weight: bold; }
                .notes { margin-top: 25px; color: #475569; white-space: pre-wrap; }
                .sign { display: flex; justify-content: space-between; gap: 30px; margin-top: 60px; break-inside: avoid; }
                .sign div { width: 220px; border-top: 1px solid #94a3b8; padding-top: 8px; color: #64748b; font-size: 11px; text-align: center; }
                @page { size: A4; margin: 14mm; }
                @media print {
                    body * { visibility: hidden !important; }
                    #institute-other-bill-print-root,
                    #institute-other-bill-print-root * { visibility: visible !important; }
                    #institute-other-bill-print-root { display: block !important; position: fixed; inset: 0; width: 100%; background: #fff; }
                    .paper { max-width: none; margin: 0; padding: 0; }
                    .header, .summary { break-inside: avoid; }
                    .items tr { break-inside: avoid; page-break-inside: avoid; }
                }
            `}</style>
            <main className="paper">
                <header className="header">
                    <div className="identity">
                        <img className="logo" src={invoiceHeader.logo_url} alt={`${invoiceHeader.name} logo`} />
                        <div className="contact">
                            {invoiceHeader.address && <p><b>Address:</b> {invoiceHeader.address}</p>}
                            {invoiceHeader.email && <p><b>Email:</b> {invoiceHeader.email}</p>}
                            {invoiceHeader.phone && <p><b>Phone:</b> {invoiceHeader.phone}</p>}
                        </div>
                    </div>
                    <div className="title">
                        <p className="invoice-id">{bill.bill_number}</p>
                    </div>
                </header>
                <section className="summary">
                    <div><div className="label">Bill name</div><div className="value">{bill.university_name}</div></div>
                    <div><div className="label">Bill date</div><div className="value">{bill.bill_date}</div></div>
                    <div><div className="label">Due date</div><div className="value">{bill.due_on || '—'}</div></div>
                    <div><div className="label">Issued by</div><div className="value">{issuer}</div></div>
                </section>
                <table className="items">
                    <thead><tr><th>#</th><th>Bill item</th><th className="num">Qty</th><th className="num">Unit price</th><th className="num">Line total</th></tr></thead>
                    <tbody>{bill.bill_items.map((item, index) => (
                        <tr key={`${bill.bill_number}-${index}`}>
                            <td>{index + 1}</td><td>{item.description}</td><td className="num">{item.quantity}</td>
                            <td className="num">{formatMoney(item.unit_price)}</td><td className="num">{formatMoney(item.line_total)}</td>
                        </tr>
                    ))}</tbody>
                </table>
                <div className="total"><div><span>Total bill</span><span>{formatMoney(bill.total_amount)}</span></div></div>
                {bill.notes && <p className="notes"><b>Notes:</b> {bill.notes}</p>}
                <footer className="sign"><div>Authorized signature</div><div>Recipient acknowledgment</div></footer>
            </main>
        </>
    );
}
