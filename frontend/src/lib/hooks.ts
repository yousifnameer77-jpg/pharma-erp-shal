"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { Paginated, PaginationMeta } from "@/lib/types";

type Query = Record<string, string | number | boolean | undefined | null>;

interface CacheEntry<T> {
  data: T;
  meta?: PaginationMeta | null;
  timestamp: number;
}

const globalCache = new Map<string, CacheEntry<any>>();
const inFlightRequests = new Map<string, Promise<any>>();

export function getCached<T>(key: string, maxAgeMs = 120000): CacheEntry<T> | null {
  const entry = globalCache.get(key);
  if (!entry) return null;
  if (Date.now() - entry.timestamp > maxAgeMs) {
    return null;
  }
  return entry;
}

export function setCached<T>(key: string, data: T, meta?: PaginationMeta | null): void {
  globalCache.set(key, {
    data,
    meta: meta ?? null,
    timestamp: Date.now(),
  });
}

export function invalidateCache(pathPrefix?: string): void {
  if (!pathPrefix) {
    globalCache.clear();
    return;
  }
  for (const key of globalCache.keys()) {
    if (key.startsWith(pathPrefix)) {
      globalCache.delete(key);
    }
  }
}

/** Eagerly prefetch a resource into memory so pages/tabs open in 0ms */
export async function prefetchResource(path: string, query: Query = {}): Promise<void> {
  const queryKey = JSON.stringify(query);
  const cacheKey = `${path}::${queryKey}`;
  if (globalCache.has(cacheKey)) return;

  if (inFlightRequests.has(cacheKey)) {
    try {
      await inFlightRequests.get(cacheKey);
    } catch {
      // Ignore prefetch errors
    }
    return;
  }

  const promise = api.get<any>(path, query);
  inFlightRequests.set(cacheKey, promise);

  try {
    const res = await promise;
    if (res && typeof res === "object") {
      if (Array.isArray(res.data)) {
        setCached(cacheKey, res.data, res.meta ?? null);
      } else if (res.data !== undefined) {
        setCached(cacheKey, res.data);
      } else {
        setCached(cacheKey, res);
      }
    }
  } catch {
    // Ignore prefetch errors
  } finally {
    inFlightRequests.delete(cacheKey);
  }
}

/**
 * Fetches a Laravel `->paginate()` endpoint with Stale-While-Revalidate (SWR) in-memory cache.
 * Returns instant data if previously cached (0ms delay), updating quietly in background.
 */
export function usePaginatedResource<T>(path: string, query: Query = {}) {
  const [page, setPage] = useState(1);
  const [reloadKey, setReloadKey] = useState(0);
  const queryKey = JSON.stringify(query);
  const cacheKey = `${path}::${queryKey}::p${page}`;

  const cached = globalCache.get(cacheKey);

  const [data, setData] = useState<T[]>(() => cached?.data ?? []);
  const [meta, setMeta] = useState<PaginationMeta | null>(() => cached?.meta ?? null);
  const [loading, setLoading] = useState(() => !cached);
  const [error, setError] = useState<string | null>(null);

  const refetch = useCallback(() => {
    invalidateCache(path);
    setReloadKey((k) => k + 1);
  }, [path]);

  useEffect(() => {
    setPage(1);
  }, [queryKey, path]);

  useEffect(() => {
    let cancelled = false;
    const currentCached = globalCache.get(cacheKey);

    // If cached and fresh (under 15s) and not forced reload, keep cached
    const isFresh = currentCached && Date.now() - currentCached.timestamp < 15000 && reloadKey === 0;

    if (currentCached) {
      setData(currentCached.data);
      setMeta(currentCached.meta ?? null);
      setLoading(false);
      if (isFresh) return;
    } else {
      setLoading(true);
    }

    setError(null);

    // Deduplicate in-flight requests
    let requestPromise = inFlightRequests.get(cacheKey);
    if (!requestPromise) {
      requestPromise = api.get<Paginated<T>>(path, { ...JSON.parse(queryKey), page });
      inFlightRequests.set(cacheKey, requestPromise);
    }

    requestPromise
      .then((res: Paginated<T>) => {
        if (cancelled) return;
        const resultData = res.data ?? [];
        const resultMeta = res.meta ?? null;
        setCached(cacheKey, resultData, resultMeta);
        setData(resultData);
        setMeta(resultMeta);
      })
      .catch((err: any) => {
        if (cancelled) return;
        setError(
          err instanceof ApiError ? err.summary : "تعذر تحميل البيانات.",
        );
        if (!currentCached) {
          setData([]);
          setMeta(null);
        }
      })
      .finally(() => {
        inFlightRequests.delete(cacheKey);
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [path, queryKey, page, reloadKey, cacheKey]);

  return { data, meta, loading, error, page, setPage, refetch };
}

/** Fetches a plain (non-paginated) `{ data: [...] }` list endpoint with instant SWR cache. */
export function useSimpleList<T>(
  path: string,
  query: Query = {},
  enabled = true,
) {
  const queryKey = JSON.stringify(query);
  const cacheKey = `${path}::${queryKey}`;
  const cached = globalCache.get(cacheKey);

  const [data, setData] = useState<T[]>(() => cached?.data ?? []);
  const [loading, setLoading] = useState(() => (enabled ? !cached : false));
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const refetch = useCallback(() => {
    invalidateCache(path);
    setReloadKey((k) => k + 1);
  }, [path]);

  useEffect(() => {
    if (!enabled) {
      setLoading(false);
      return;
    }

    let cancelled = false;
    const currentCached = globalCache.get(cacheKey);
    const isFresh = currentCached && Date.now() - currentCached.timestamp < 30000 && reloadKey === 0;

    if (currentCached) {
      setData(currentCached.data);
      setLoading(false);
      if (isFresh) return;
    } else {
      setLoading(true);
    }

    setError(null);

    let requestPromise = inFlightRequests.get(cacheKey);
    if (!requestPromise) {
      requestPromise = api.get<{ data: T[] }>(path, JSON.parse(queryKey));
      inFlightRequests.set(cacheKey, requestPromise);
    }

    requestPromise
      .then((res: { data: T[] }) => {
        if (cancelled) return;
        const resultData = res.data ?? [];
        setCached(cacheKey, resultData);
        setData(resultData);
      })
      .catch((err: any) => {
        if (cancelled) return;
        setError(
          err instanceof ApiError ? err.summary : "تعذر تحميل البيانات.",
        );
        if (!currentCached) setData([]);
      })
      .finally(() => {
        inFlightRequests.delete(cacheKey);
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [path, queryKey, reloadKey, enabled, cacheKey]);

  return { data, loading, error, refetch };
}

/** Fetches a single `{ data: {...} }` resource with instant SWR cache and deduplication. */
export function useApiResource<T>(path: string | null, query: Query = {}) {
  const queryKey = JSON.stringify(query);
  const cacheKey = path ? `${path}::${queryKey}` : null;
  const cached = cacheKey ? globalCache.get(cacheKey) : null;

  const [data, setData] = useState<T | null>(() => cached?.data ?? null);
  const [loading, setLoading] = useState(() => (path ? !cached : false));
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const refetch = useCallback(() => {
    if (path) invalidateCache(path);
    setReloadKey((k) => k + 1);
  }, [path]);

  useEffect(() => {
    if (!path || !cacheKey) {
      setData(null);
      setLoading(false);
      return;
    }

    let cancelled = false;
    const currentCached = globalCache.get(cacheKey);
    const isFresh = currentCached && Date.now() - currentCached.timestamp < 15000 && reloadKey === 0;

    if (currentCached) {
      setData(currentCached.data);
      setLoading(false);
      if (isFresh) return;
    } else {
      setLoading(true);
    }

    setError(null);

    let requestPromise = inFlightRequests.get(cacheKey);
    if (!requestPromise) {
      requestPromise = api.get<{ data: T }>(path, JSON.parse(queryKey));
      inFlightRequests.set(cacheKey, requestPromise);
    }

    requestPromise
      .then((res: { data: T }) => {
        if (cancelled) return;
        setCached(cacheKey, res.data);
        setData(res.data);
      })
      .catch((err: any) => {
        if (cancelled) return;
        setError(
          err instanceof ApiError ? err.summary : "تعذر تحميل البيانات.",
        );
        if (!currentCached) setData(null);
      })
      .finally(() => {
        inFlightRequests.delete(cacheKey);
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [path, queryKey, reloadKey, cacheKey]);

  return { data, loading, error, refetch };
}
