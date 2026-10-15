#!/bin/bash
# Account deletion on the website: password users, Google sign-in users (who
# confirm with their email), wrong confirmations, CSRF, data cascade. usage: ./account_delete_test.sh
B=${BASE:-http://127.0.0.1:8090/}
Q="psql ${PSQL_ARGS:--h /tmp/jlpg -p 5433 -d aitut} -Atq -c"
pass=0; fail=0
ok(){ if [ "$1" = "$2" ]; then pass=$((pass+1)); echo "PASS $3"; else fail=$((fail+1)); echo "FAIL $3 (got '$1' want '$2')"; fi; }
mkuser(){ # email google_id password -> id
  local h; h=$(php -r 'echo password_hash($argv[1], PASSWORD_BCRYPT);' "$3")
  $Q "INSERT INTO users(email,password,name,google_id,email_verified_at,plan_status,onboarding_completed) VALUES ('$1','$h','Del Test',NULLIF('$2',''),now(),'trial',1) RETURNING id" | head -1
}
login(){ # id jar -> sets PHPSESSID cookie in jar (session row written straight to the DB)
  local sid="deltest$RANDOM$RANDOM"
  $Q "INSERT INTO sessions(id,data,expires_at) VALUES ('$sid','user_id|i:$1;',now()+interval '1 hour')"
  printf "127.0.0.1\tFALSE\t/\tFALSE\t0\tPHPSESSID\t%s\n" "$sid" > "$2"
}
csrf(){ curl -s -b "$1" -c "$1" "${B}?page=account-delete-confirm" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
exists(){ $Q "SELECT count(*) FROM users WHERE id=$1"; }
post(){ curl -s -o /dev/null -w '%{redirect_url}' -b "$1" -c "$1" -X POST "${B}?page=account-delete" --data-urlencode "csrf_token=$2" --data-urlencode "password=$3" --data-urlencode "confirm_email=$4"; }
J=$(mktemp)
R=$RANDOM

# 1. public page needs no login; confirm page does
ok "$(curl -s -o /dev/null -w '%{http_code}' ${B}delete-account)" 200 "public /delete-account is 200 without login"
ok "$(curl -s -o /dev/null -w '%{http_code}' "${B}?page=account-delete-confirm")" 302 "confirm page redirects when logged out"

# 2. password user: form shows password field, not email field
U=$(mkuser "pw$R@example.test" "" "Secret123!"); login $U $J
page=$(curl -s -b $J -c $J "${B}?page=account-delete-confirm")
ok "$(echo "$page" | grep -c 'name="password"')" 1 "password user sees password field"
ok "$(echo "$page" | grep -c 'name="confirm_email"')" 0 "password user has no email field"
T=$(csrf $J)
post $J "$T" "" "pw$R@example.test" >/dev/null
ok "$(exists $U)" 1 "password user cannot delete with email only"
post $J "$T" "wrong" "" >/dev/null
ok "$(exists $U)" 1 "wrong password keeps account"
post $J "bogus" "Secret123!" "" >/dev/null
ok "$(exists $U)" 1 "bad CSRF token keeps account"
loc=$(post $J "$T" "Secret123!" "")
ok "$(exists $U)" 0 "right password deletes account"
ok "$(echo $loc | grep -c account_deleted=1)" 1 "redirects home with account_deleted=1"

# 3. Google user (random password they never saw)
G=$(mkuser "gg$R@example.test" "g-$R" "$(head -c 24 /dev/urandom | base64)"); login $G $J
page=$(curl -s -b $J -c $J "${B}?page=account-delete-confirm")
ok "$(echo "$page" | grep -c 'name="confirm_email"')" 1 "Google user sees email field"
ok "$(echo "$page" | grep -c 'name="password"')" 0 "Google user has no password field"
T=$(csrf $J)
post $J "$T" "" "someone-else@example.test" >/dev/null
ok "$(exists $G)" 1 "Google user: wrong email keeps account"
err=$(curl -s -b $J -c $J "${B}?page=account-delete-confirm" | grep -c "isn&#039;t the email address\|isn't the email address")
ok "$err" 1 "Google user: mismatch message shown"
post $J "$T" "" "" >/dev/null
ok "$(exists $G)" 1 "Google user: empty confirmation keeps account"
post $J "$T" "" "  GG$R@EXAMPLE.TEST " >/dev/null
ok "$(exists $G)" 0 "Google user: email (any case, padded) deletes account"

# 4. cascade: a deleted user's data is gone
C=$(mkuser "cc$R@example.test" "" "Secret123!")
$Q "INSERT INTO conversations(user_id) VALUES ($C)" 2>/dev/null
login $C $J; T=$(csrf $J); post $J "$T" "Secret123!" "" >/dev/null
ok "$(exists $C)" 0 "cascade user deleted"
ok "$($Q "SELECT count(*) FROM conversations WHERE user_id=$C")" 0 "their conversations are gone"
ok "$($Q "SELECT count(*) FROM activity_events WHERE event_type='account_deleted' AND detail LIKE '%cc$R@example.test%'")" 1 "deletion record kept (as the policy says)"

rm -f $J
echo "TOTAL $((pass+fail))  PASS $pass  FAIL $fail"
[ $fail = 0 ]
