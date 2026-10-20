# Dodo webhooks and the checkout-return check, against local staging: signed
# events change the plan, a failed or pending checkout never touches a trial
# or a paid plan, duplicates and old subscriptions are ignored, and
# check-payment-status only reports a real payment (not a free trial).
# Needs the server started with the same secret:
#   DODO_WEBHOOK_SECRET=whsec_cWEtbG9jYWwtdGVzdC1zZWNyZXQtMTIzNDU2 php -S 127.0.0.1:8090 -t public
# usage: python3 tests/e2e/billing_test.py
import base64, hashlib, hmac, json, os, shlex, subprocess, time, urllib.request, urllib.error

BASE = os.environ.get('BASE', 'http://127.0.0.1:8090/')
SECRET = os.environ.get('DODO_WEBHOOK_SECRET', 'whsec_cWEtbG9jYWwtdGVzdC1zZWNyZXQtMTIzNDU2')
PSQL = ['psql'] + shlex.split(os.environ.get('PSQL_ARGS', '-h /tmp/jlpg -p 5433 -d aitut')) + ['-Atqc']
PRO = 'pdt_0NnjYs5i3QuMtGjC1gUqq'  # config.php default: pro / month
n = fails = 0


def sql(q):
    return subprocess.run(PSQL + [q], capture_output=True, text=True).stdout.strip()


def ok(got, want, name):
    global n, fails
    n += 1
    if got == want:
        print('PASS', name)
    else:
        fails += 1
        print(f'FAIL {name} (got {got!r} want {want!r})')


def http(url, data=None, headers=None):
    req = urllib.request.Request(url, data=data, headers=headers or {}, method='POST' if data is not None else 'GET')
    try:
        with urllib.request.urlopen(req) as r:
            return r.status, r.read().decode()
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode()


def send(evt, sub_id, status, uid, wid=None, product=PRO, sig_secret=SECRET):
    body = json.dumps({'type': evt, 'data': {
        'subscription_id': sub_id, 'product_id': product, 'status': status,
        'metadata': {'user_id': str(uid)}, 'customer': {'customer_id': 'cus_billing_test'},
        'next_billing_date': '2030-01-01T00:00:00Z'}})
    wid = wid or 'msg_billing_%d_%d' % (time.time_ns(), uid)
    ts = str(int(time.time()))
    key = base64.b64decode(sig_secret[len('whsec_'):])
    sig = base64.b64encode(hmac.new(key, f'{wid}.{ts}.{body}'.encode(), hashlib.sha256).digest()).decode()
    return http(BASE + 'dodo-webhook.php', body.encode(), {
        'webhook-id': wid, 'webhook-timestamp': ts, 'webhook-signature': 'v1,' + sig, 'Content-Type': 'application/json'})[0]


def user(plan, has_paid=0, sub=None):
    tag = '%d%d' % (time.time_ns(), os.getpid())
    return int(sql(f"""INSERT INTO users (email, password, name, plan_status, has_paid, dodo_subscription_id,
        onboarding_completed, email_verified_at, native_lang, target_lang)
        VALUES ('billing{tag}@example.test', 'x', 'Billing Test', '{plan}', {has_paid}, {f"'{sub}'" if sub else 'NULL'},
        1, now(), 'en', 'es') RETURNING id""").splitlines()[0])


def state(uid):
    return sql(f"SELECT plan_status || '/' || has_paid FROM users WHERE id = {uid}")


def paid_flag(uid):
    sid = 'billingtest%d' % time.time_ns()
    sql(f"INSERT INTO sessions (id, data, expires_at) VALUES ('{sid}', 'user_id|i:{uid};', now() + interval '1 hour')")
    code, body = http(BASE + '?page=check-payment-status', headers={'Cookie': 'PHPSESSID=' + sid})
    sql(f"DELETE FROM sessions WHERE id = '{sid}'")
    return json.loads(body).get('paid') if code == 200 else code


# signature checks
u = user('trial')
ok(send('subscription.active', 'sub_bt_sig', 'active', u, sig_secret='whsec_' + base64.b64encode(b'wrong').decode()), 401, 'wrong signature -> 401')
ok(state(u), 'trial/0', 'wrong signature changes nothing')

# a failed or pending first checkout keeps the free trial
ok(send('subscription.failed', 'sub_bt_fail', 'failed', u), 200, 'failed checkout accepted (200)')
ok(state(u), 'trial/0', 'failed first payment keeps the trial')
ok(send('subscription.on_hold', 'sub_bt_hold', 'pending', u), 200, 'pending event accepted (200)')
ok(state(u), 'trial/0', 'pending subscription grants nothing')
ok(paid_flag(u), False, 'trial is not reported as paid')

# activation, duplicate, renewal
ok(send('subscription.active', 'sub_bt_1', 'active', u), 200, 'activation accepted')
ok(state(u), 'pro/1', 'activation -> pro, paid')
ok(sql(f'SELECT dodo_subscription_id FROM users WHERE id = {u}'), 'sub_bt_1', 'subscription linked')
ok(paid_flag(u), True, 'paid plan reported as paid')
wid = 'msg_billing_dup_%d' % time.time_ns()
send('subscription.renewed', 'sub_bt_1', 'active', u, wid)
ok(send('subscription.renewed', 'sub_bt_1', 'active', u, wid), 200, 'duplicate webhook id accepted')
ok(state(u), 'pro/1', 'duplicate changes nothing')

# a failed checkout for a second subscription must not end the paid one
send('subscription.failed', 'sub_bt_other', 'failed', u)
ok(state(u), 'pro/1', 'failed second checkout keeps the paid plan')
send('subscription.cancelled', 'sub_bt_old', 'cancelled', u)
ok(state(u), 'pro/1', 'cancellation of an old subscription ignored')
send('subscription.on_hold', 'sub_bt_1', 'pending', u)
ok(state(u), 'pro/1', 'pending status on the current subscription changes nothing')

# the current subscription ending does end the plan
send('subscription.on_hold', 'sub_bt_1', 'on_hold', u)
ok(state(u), 'canceled/0', 'on_hold of the current subscription ends the plan')
ok(paid_flag(u), False, 'canceled not reported as paid')

# a plan given by hand (no Dodo subscription) survives a failed checkout
m = user('starter', 1)
send('subscription.failed', 'sub_bt_m', 'failed', m)
ok(state(m), 'starter/1', 'manual plan survives a failed checkout')

sql(f"DELETE FROM users WHERE id IN ({u}, {m})")
print(f'TOTAL {n}  PASS {n - fails}  FAIL {fails}')
raise SystemExit(1 if fails else 0)
