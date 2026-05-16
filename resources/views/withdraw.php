<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Withdraw to Stellar</title>
</head>
<body>
    <h1>Withdraw to Stellar</h1>
    <form id="withdraw-form">
        <label>Amount
            <input type="number" id="amount" step="0.01" min="0.01" required />
        </label>
        <label>Stellar Address
            <input type="text" id="address" maxlength="56" required />
        </label>
        <button type="submit">Submit</button>
    </form>
    <pre id="out"></pre>
<script>
const form = document.getElementById('withdraw-form');
const out = document.getElementById('out');
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const amount = document.getElementById('amount').value.trim();
    const address = document.getElementById('address').value.trim();
    const res = await fetch('/api/stellar/withdraw', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ amount, address })
    });
    out.textContent = await res.text();
});
</script>
</body>
</html>
