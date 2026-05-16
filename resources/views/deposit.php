<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Deposit from Stellar</title>
</head>
<body>
    <h1>Deposit from Stellar</h1>
    <form id="deposit-form">
        <label>Stellar Transaction Hash
            <input type="text" id="txn_hash" maxlength="64" required />
        </label>
        <button type="submit">Submit</button>
    </form>
    <pre id="out"></pre>
<script>
const form = document.getElementById('deposit-form');
const out = document.getElementById('out');
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const txn = document.getElementById('txn_hash').value.trim();
    const res = await fetch('/api/stellar/deposit', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ txn_hash: txn })
    });
    out.textContent = await res.text();
});
</script>
</body>
</html>
