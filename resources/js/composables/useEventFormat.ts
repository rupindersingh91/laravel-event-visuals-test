export interface EventFormatUtils {
    formatDate: (isoString: string | null) => string;
    formatPrice: (price: number | null) => string;
}

export function useEventFormat(): EventFormatUtils {
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;

    const dateFormatter = new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: tz,
    });

    const priceFormatter = new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });

    function formatDate(isoString: string | null): string {
        if (!isoString) return '—';
        try {
            return dateFormatter.format(new Date(isoString));
        } catch {
            return '—';
        }
    }

    function formatPrice(price: number | null): string {
        if (price === null || price === undefined) return 'Free';
        if (price === 0) return 'Free';
        try {
            return priceFormatter.format(price);
        } catch {
            return `$${String(price)}`;
        }
    }

    return { formatDate, formatPrice };
}
