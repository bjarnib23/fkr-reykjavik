export function preloadImages(urls) {
  const load = urls && urls.length > 0
    ? Promise.allSettled(urls.map(src => new Promise(resolve => {
        const img = new window.Image()
        img.onload = resolve
        img.onerror = resolve
        img.src = src
      })))
    : Promise.resolve()
  return Promise.all([load, new Promise(r => setTimeout(r, 1000))])
}
