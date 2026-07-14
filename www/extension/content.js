// Heuristika: najdi DOI v textu nebo v meta značkách
const DOI_REGEX = /\b10\.\d{4,9}\/[-._;()/:A-Z0-9]+/i;

function findDOI() {
    // 1) meta tagy
    const metas = [
        'meta[name="citation_doi"]',
        'meta[name="dc.identifier"]',
        'meta[property="og:doi"]'
    ];
    for (const sel of metas) {
        const el = document.querySelector(sel);
        const val = el?.getAttribute("content") || el?.getAttribute("value");
        if (val && DOI_REGEX.test(val)) return val.match(DOI_REGEX)[0];
    }
    // 2) JSON-LD
    for (const script of document.querySelectorAll('script[type="application/ld+json"]')) {
        try {
            const data = JSON.parse(script.textContent || "{}");
            const doi = data?.identifier?.value || data?.doi;
            if (doi && DOI_REGEX.test(doi)) return doi.match(DOI_REGEX)[0];
        } catch {}
    }
    // 3) fallback: body text
    const m = document.body.innerText.match(DOI_REGEX);
    return m ? m[0] : null;
}

// Volitelně: publikuj event na background (např. auto-lookup)
(async () => {
    const doi = findDOI();
    if (doi) {
        chrome.runtime.sendMessage({ type: "FOUND_DOI", doi });
    }
})();
