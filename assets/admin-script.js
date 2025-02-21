function updateHiddenInput() {
    let data = {};
    tableBody.querySelectorAll("tr").forEach(row => {
        const keyword = row.querySelector(".keyword-input").value.trim();
        const url = row.querySelector(".url-input").value.trim();
        const limit = row.querySelector(".limit-input").value.trim() || 1;

        if (keyword && url) {
            data[keyword] = { url: url, limit: parseInt(limit) };
        }
    });
    hiddenInput.value = JSON.stringify(data);
}
