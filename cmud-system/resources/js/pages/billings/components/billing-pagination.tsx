import { ChevronLeft, ChevronRight } from 'lucide-react';

type BillingPaginationProps = {
    page: number;
    lastPage: number;
    perPage: number;
    total: number;
    loading?: boolean;
    recordLabel: string;
    onPageChange: (page: number) => void;
};

/** Shared numbered paginator for the billing registries. */
export default function BillingPagination({
    page,
    lastPage,
    perPage,
    total,
    loading = false,
    recordLabel,
    onPageChange,
}: BillingPaginationProps) {
    const safeLastPage = Math.max(lastPage, 1);
    const visiblePageCount = 5;
    const start = Math.min(
        Math.max(1, page - Math.floor(visiblePageCount / 2)),
        Math.max(1, safeLastPage - visiblePageCount + 1),
    );
    const end = Math.min(safeLastPage, start + visiblePageCount - 1);
    const pages: Array<number | '…'> = [];

    if (safeLastPage <= visiblePageCount + 1) {
        for (let pageNumber = 1; pageNumber <= safeLastPage; pageNumber += 1) pages.push(pageNumber);
    } else {
        if (start > 1) {
            pages.push(1);
            if (start > 2) pages.push('…');
        }
        for (let pageNumber = start; pageNumber <= end; pageNumber += 1) pages.push(pageNumber);
        if (end < safeLastPage) {
            if (end < safeLastPage - 1) pages.push('…');
            pages.push(safeLastPage);
        }
    }

    const firstRecord = total === 0 ? 0 : (page - 1) * perPage + 1;
    const lastRecord = Math.min(page * perPage, total);

    return (
        <footer className="flex flex-col justify-between gap-3 border-t border-slate-100 px-5 py-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:px-6">
            <span>
                Showing <strong className="text-slate-700">{firstRecord}–{lastRecord}</strong> of{' '}
                <strong className="text-slate-700">{total}</strong> {recordLabel}
            </span>
            <nav className="flex items-center gap-1" aria-label={`${recordLabel} pages`}>
                <button
                    type="button"
                    disabled={loading || page <= 1}
                    onClick={() => onPageChange(Math.max(1, page - 1))}
                    aria-label="Previous page"
                    className="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 text-slate-500 disabled:text-slate-300"
                >
                    <ChevronLeft size={16} />
                </button>
                {pages.map((item, index) => item === '…'
                    ? <span key={`ellipsis-${index}`} className="px-1 text-slate-400" aria-hidden="true">…</span>
                    : <button
                        key={item}
                        type="button"
                        disabled={loading}
                        aria-current={item === page ? 'page' : undefined}
                        onClick={() => onPageChange(item)}
                        className={`grid h-8 min-w-8 place-items-center rounded-lg px-2 ${item === page ? 'bg-[#1B365D] font-semibold text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50'}`}
                    >{item}</button>)}
                <button
                    type="button"
                    disabled={loading || page >= safeLastPage}
                    onClick={() => onPageChange(Math.min(safeLastPage, page + 1))}
                    aria-label="Next page"
                    className="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 text-slate-500 disabled:text-slate-300"
                >
                    <ChevronRight size={16} />
                </button>
            </nav>
        </footer>
    );
}
