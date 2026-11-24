window.copyGroupFolio = async function (folio) {
    if (!folio || !navigator.clipboard?.writeText) return false;

    try {
        await navigator.clipboard.writeText(folio);
        return true;
    } catch {
        return false;
    }
};
