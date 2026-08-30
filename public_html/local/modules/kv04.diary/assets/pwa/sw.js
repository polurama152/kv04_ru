/*
 * Сервис-воркер дневника. Две обязанности: статика мгновенно из кэша и
 * честная страница «нет сети». HTML и всё приватное в Cache Storage не
 * попадает никогда — иначе заметки читались бы из кэша в обход пина.
 * Отдаётся посредником pub/sw.php с пути дневника — scope равен этому пути.
 */
const CACHE = 'kv04-diary-v1';
const OFFLINE_URL = '/local/modules/kv04.diary/assets/pwa/offline.html';

self.addEventListener('install', (event) => {
	event.waitUntil(
		caches.open(CACHE)
			.then((cache) => cache.add(OFFLINE_URL))
			.then(() => self.skipWaiting())
	);
});

self.addEventListener('activate', (event) => {
	event.waitUntil(
		caches.keys()
			.then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
			.then(() => self.clients.claim())
	);
});

// Кэшу доверено только неизменяемое: статика уходит с ?v=<mtime>, поэтому
// новая версия файла — это новый URL, а не устаревшая запись в кэше.
const isStatic = (url) =>
	url.origin === self.location.origin
	&& (url.pathname.startsWith('/local/') || url.pathname === '/favicon.ico')
	&& !url.pathname.endsWith('.php');

self.addEventListener('fetch', (event) => {
	const request = event.request;
	if (request.method !== 'GET') {
		return;
	}

	if (request.mode === 'navigate') {
		event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
		return;
	}

	const url = new URL(request.url);
	if (!isStatic(url)) {
		return;
	}

	event.respondWith(
		caches.match(request).then((hit) => hit || fetch(request).then((response) => {
			if (response.ok) {
				const copy = response.clone();
				caches.open(CACHE).then((cache) => cache.put(request, copy));
			}
			return response;
		}))
	);
});
