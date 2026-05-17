import { onMounted, onUnmounted, ref } from 'vue';

const toDateStr = (d: Date): string =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

/**
 * Returns a reactive `todayStr` (YYYY-MM-DD) that automatically updates at
 * midnight so upcoming/past event classifications stay correct in long-lived
 * sessions without requiring a page refresh.
 */
export function useToday() {
    const todayStr = ref(toDateStr(new Date()));
    let timerId: ReturnType<typeof setTimeout>;

    const scheduleNextUpdate = () => {
        const now = new Date();
        const midnight = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1);
        const msUntilMidnight = midnight.getTime() - now.getTime();

        timerId = setTimeout(() => {
            todayStr.value = toDateStr(new Date());
            scheduleNextUpdate();
        }, msUntilMidnight);
    };

    onMounted(scheduleNextUpdate);
    onUnmounted(() => clearTimeout(timerId));

    return { todayStr };
}
