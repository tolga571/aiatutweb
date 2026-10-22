import json, os, re, shlex, subprocess, sys, time, urllib.request, urllib.parse, urllib.error
from http.cookiejar import CookieJar

BASE = os.environ.get('BASE', 'http://127.0.0.1:8090/').rstrip('/') + '/'
PSQL = ['psql'] + shlex.split(os.environ.get('PSQL_ARGS', '-h /tmp/jlpg -p 5433 -d aitut')) + ['-Atqc']

n = fails = 0

def sql(q):
    res = subprocess.run(PSQL + [q], capture_output=True, text=True)
    if res.returncode != 0:
        raise RuntimeError(f"psql error: {res.stderr}")
    return res.stdout.strip()

def make_token_for_user(uid):
    # Same row Auth::createEmailVerificationToken() writes (sha256 of the token),
    # straight into the database the server under test uses.
    token = 'regtest%d%d' % (uid, int(time.time() * 1000))
    sql(f"INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES ({uid}, encode(sha256('{token}'), 'hex'), now() + interval '1 day')")
    return token

def ok(got, want, name):
    global n, fails
    n += 1
    if got == want:
        print(f'PASS: {name}')
    else:
        fails += 1
        print(f'FAIL: {name} (got {got!r}, want {want!r})')

def true(cond, name, msg=''):
    global n, fails
    n += 1
    if cond:
        print(f'PASS: {name}')
    else:
        fails += 1
        print(f'FAIL: {name} ({msg})')

class NoRedirectHandler(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

def create_client():
    cj = CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), NoRedirectHandler)
    return opener, cj

print("--- Running Jumplearner Registration Flow & Verification Tests ---")

# 1. Web registration: disposable email rejection
print("\n[Test 1] Web registration rejects disposable email")
client, cj = create_client()
req = urllib.request.Request(BASE + '?page=register')
resp = client.open(req)
html = resp.read().decode('utf-8')
csrf_match = re.search(r'name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']', html)
true(bool(csrf_match), "Obtained CSRF token from registration page")
csrf_token = csrf_match.group(1) if csrf_match else ''

disp_email = f"disposable_{time.time_ns()}@tempmail.com"
post_data = urllib.parse.urlencode({
    'csrf_token': csrf_token,
    'email': disp_email,
    'name': 'Disposable Test',
    'password': 'Password123!',
    'password_confirm': 'Password123!',
    'terms': '1'
}).encode('utf-8')

post_req = urllib.request.Request(BASE + '?page=register', data=post_data, method='POST')
try:
    post_resp = client.open(post_req)
    loc = post_resp.headers.get('Location')
except urllib.error.HTTPError as e:
    loc = e.headers.get('Location')

db_count = sql(f"SELECT count(*) FROM users WHERE email = '{disp_email}'")
ok(db_count, "0", "Disposable email user was not created in DB")
true(loc is None or "awaiting-verification" not in loc, "Did not redirect to awaiting-verification on disposable email")


# 2. Mobile API: disposable email rejection
print("\n[Test 2] Mobile API rejects disposable email with 422")
api_disp_email = f"apidisp_{time.time_ns()}@guerrillamail.com"
api_payload = json.dumps({
    'email': api_disp_email,
    'name': 'API Disposable',
    'password': 'Password123!',
    'accept_terms': True
}).encode('utf-8')
api_req = urllib.request.Request(BASE + 'api/v1/auth/register', data=api_payload, headers={'Content-Type': 'application/json'}, method='POST')
try:
    with urllib.request.urlopen(api_req) as r:
        api_status, api_data = r.status, json.loads(r.read().decode('utf-8'))
except urllib.error.HTTPError as e:
    api_status = e.code
    api_data = json.loads(e.read().decode('utf-8'))

ok(api_status, 422, "Mobile API returns 422 for disposable email")
err = api_data.get('error', {})
err_msg = err.get('message', '') if isinstance(err, dict) else str(err)
true('temporary' in err_msg.lower() or 'disposable' in err_msg.lower(), "Mobile API error identifies temporary/disposable email")
db_count_api = sql(f"SELECT count(*) FROM users WHERE email = '{api_disp_email}'")
ok(db_count_api, "0", "Mobile disposable email was not created in DB")


# 3. Web Registration -> Verification link -> Auto-login
print("\n[Test 3] Web registration, email verification, and automatic session login")
client3, cj3 = create_client()
resp = client3.open(urllib.request.Request(BASE + '?page=register'))
html = resp.read().decode('utf-8')
csrf_token = re.search(r'name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']', html).group(1)

web_email = f"autologin_{time.time_ns()}@example.test"
post_data = urllib.parse.urlencode({
    'csrf_token': csrf_token,
    'email': web_email,
    'name': 'Auto Login Test',
    'password': 'Password123!',
    'password_confirm': 'Password123!',
    'terms': '1'
}).encode('utf-8')

post_req = urllib.request.Request(BASE + '?page=register', data=post_data, method='POST')
try:
    post_resp = client3.open(post_req)
    loc = post_resp.headers.get('Location')
except urllib.error.HTTPError as e:
    loc = e.headers.get('Location')

true(loc is not None and '?page=awaiting-verification' in loc, "Registration redirects to awaiting-verification")

# Verify user exists in DB and is unverified (email_verified_at IS NULL)
uid = sql(f"SELECT id FROM users WHERE email = '{web_email}'")
true(bool(uid), f"User created in DB with id={uid}")
ev_at = sql(f"SELECT email_verified_at FROM users WHERE id = {uid}")
ok(ev_at, "", "User email_verified_at is NULL before verification")

# Generate token to test the verify link
token = make_token_for_user(int(uid))
true(len(token) > 10, "Verification token created")

# User clicks verification link: ?page=verify-email&token=...
verify_req = urllib.request.Request(BASE + f'?page=verify-email&token={urllib.parse.quote(token)}')
try:
    verify_resp = client3.open(verify_req)
    v_loc = verify_resp.headers.get('Location')
except urllib.error.HTTPError as e:
    v_loc = e.headers.get('Location')

true(v_loc is not None and '?page=dashboard&email_verified=1' in v_loc, "Verify-email redirects to dashboard with email_verified=1")

# User in DB is now verified
ev_after = sql(f"SELECT email_verified_at FROM users WHERE id = {uid}")
true(bool(ev_after), "User email_verified_at is now set in DB")
used_count = sql(f"SELECT count(*) FROM email_verifications WHERE user_id = {uid} AND used_at IS NOT NULL")
ok(used_count, "1", "email_verifications record is marked used")

# Test auto-login: access dashboard using the same session
dash_req = urllib.request.Request(BASE + '?page=dashboard')
try:
    dash_resp = client3.open(dash_req)
    d_loc = dash_resp.headers.get('Location')
except urllib.error.HTTPError as e:
    d_loc = e.headers.get('Location')

true(d_loc is not None and '?page=login' not in d_loc, "Auto-login verified: user is authenticated (not redirected to login)")
true(d_loc is not None and '?page=onboarding' in d_loc, "New verified user correctly directed to onboarding")


# 4. Mobile API: verify email endpoint
print("\n[Test 4] Mobile API email verification via POST /api/v1/auth/verify-email")
api_valid_email = f"mob_verify_{time.time_ns()}@example.test"
api_payload = json.dumps({
    'email': api_valid_email,
    'name': 'Mobile Verify Test',
    'password': 'Password123!',
    'accept_terms': True
}).encode('utf-8')
api_req = urllib.request.Request(BASE + 'api/v1/auth/register', data=api_payload, headers={'Content-Type': 'application/json'}, method='POST')
with urllib.request.urlopen(api_req) as r:
    ok(r.status, 201, "Mobile registration returned 201")
    reg_json = json.loads(r.read().decode('utf-8'))
    reg_data = reg_json.get('data', {})
    ok(reg_data.get('awaiting_verification'), True, "Mobile register response confirms awaiting_verification")

mob_uid = sql(f"SELECT id FROM users WHERE email = '{api_valid_email}'")
true(bool(mob_uid), f"Mobile user created in DB id={mob_uid}")
mob_token = make_token_for_user(int(mob_uid))

# Call POST /api/v1/auth/verify-email
v_payload = json.dumps({'token': mob_token}).encode('utf-8')
v_req = urllib.request.Request(BASE + 'api/v1/auth/verify-email', data=v_payload, headers={'Content-Type': 'application/json'}, method='POST')
with urllib.request.urlopen(v_req) as r:
    ok(r.status, 200, "Mobile verify endpoint returned 200")
    v_json = json.loads(r.read().decode('utf-8'))
    v_data = v_json.get('data', {})
    true('token' in v_data, "Mobile verify response contains Bearer token")
    true(v_data.get('user', {}).get('email_verified') is True, "Mobile verify response user has email_verified=true")
    bearer_token = v_data.get('token')

# Use Bearer token to request profile via /api/v1/me
prof_req = urllib.request.Request(BASE + 'api/v1/me', headers={'Authorization': f'Bearer {bearer_token}'})
with urllib.request.urlopen(prof_req) as r:
    ok(r.status, 200, "Authenticated /api/v1/me request with Bearer token returned 200")
    prof_json = json.loads(r.read().decode('utf-8'))
    prof_data = prof_json.get('data', {})
    ok(prof_data.get('user', {}).get('email'), api_valid_email, "Profile email matches verified user")
    ok(prof_data.get('user', {}).get('email_verified'), True, "Profile shows email_verified=True")


# 5. Database CASCADE delete on token_usage
print("\n[Test 5] Foreign key cascade on token_usage table during user deletion")
del_email = f"cascade_del_{time.time_ns()}@example.test"
del_uid = sql(f"""INSERT INTO users (email, password, name, plan_status, email_verified_at, native_lang, target_lang)
    VALUES ('{del_email}', 'dummy_pass', 'Cascade Test', 'free', now(), 'en', 'es') RETURNING id""")
true(del_uid.isdigit(), f"Created test user id={del_uid}")

# Insert a token_usage row referencing this user
sql(f"INSERT INTO token_usage (user_id, used_this_month, bonus_limit) VALUES ({del_uid}, 1234, 500)")
usage_count = sql(f"SELECT count(*) FROM token_usage WHERE user_id = {del_uid}")
ok(usage_count, "1", "token_usage record successfully inserted for user")

# Delete the user: ON DELETE CASCADE removes token_usage automatically
sql(f"DELETE FROM users WHERE id = {del_uid}")
user_exists = sql(f"SELECT count(*) FROM users WHERE id = {del_uid}")
ok(user_exists, "0", "User deleted successfully")
usage_exists = sql(f"SELECT count(*) FROM token_usage WHERE user_id = {del_uid}")
ok(usage_exists, "0", "token_usage record deleted automatically via ON DELETE CASCADE")

print("\n" + "="*50)
print(f"Total: {n}, Passed: {n - fails}, Failed: {fails}")
if fails == 0:
    print("ALL REGISTRATION & VERIFICATION TESTS PASSED!")
    sys.exit(0)
else:
    print("SOME TESTS FAILED!")
    sys.exit(1)
