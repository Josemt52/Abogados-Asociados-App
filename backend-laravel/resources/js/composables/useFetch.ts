import { onMounted, ref, watch, type Ref, type WatchSource } from 'vue';

export interface FetchState<T> {
    data: Ref<T | null>;
    loading: Ref<boolean>;
    error: Ref<string | null>;
    refetch: () => Promise<void>;
}

export const useFetch = <T>(
    fetchFunction: () => Promise<T>,
    dependencies: WatchSource[] = [],
): FetchState<T> => {
    const data = ref<T | null>(null) as Ref<T | null>;
    const loading = ref(true);
    const error = ref<string | null>(null);

    const refetch = async (): Promise<void> => {
        loading.value = true;
        error.value = null;

        try {
            data.value = await fetchFunction();
        } catch (reason) {
            data.value = null;
            error.value = reason instanceof Error ? reason.message : 'Error desconocido';
        } finally {
            loading.value = false;
        }
    };

    onMounted(refetch);

    if (dependencies.length > 0) {
        watch(dependencies, refetch);
    }

    return { data, loading, error, refetch };
};
