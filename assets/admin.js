const guestSearchForm = document.querySelector('[data-guest-search]');
const guestResults = document.querySelector('[data-guest-results]');

if (guestSearchForm && guestResults) {
  const searchInput = guestSearchForm.querySelector('input[name="q"]');
  let searchTimer;
  let searchController;

  const loadGuests = async () => {
    if (searchController) searchController.abort();

    searchController = new AbortController();
    const params = new URLSearchParams(new FormData(guestSearchForm));
    params.set('ajax', '1');
    const requestUrl = new URL(
      guestSearchForm.getAttribute('action') || window.location.pathname,
      window.location.origin
    );
    requestUrl.search = params.toString();

    try {
      const response = await fetch(requestUrl, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        signal: searchController.signal,
      });

      if (!response.ok) throw new Error('Search failed');

      guestResults.innerHTML = await response.text();

      const pageParams = new URLSearchParams(window.location.search);
      const query = searchInput.value.trim();
      if (query) {
        pageParams.set('q', query);
      } else {
        pageParams.delete('q');
      }
      pageParams.delete('ajax');

      const nextUrl = pageParams.toString()
        ? `${window.location.pathname}?${pageParams}`
        : window.location.pathname;
      window.history.replaceState({}, '', nextUrl);
    } catch (error) {
      if (error.name !== 'AbortError') {
        guestResults.innerHTML = '<tr><td colspan="4" class="empty">Unable to load guests. Please try again.</td></tr>';
      }
    }
  };

  guestSearchForm.addEventListener('submit', event => {
    event.preventDefault();
    clearTimeout(searchTimer);
    loadGuests();
  });

  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadGuests, 250);
  });
}
