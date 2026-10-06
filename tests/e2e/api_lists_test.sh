#!/bin/bash
# API smoke for card lists (mobile app endpoints). usage: ./api_lists_test.sh
B=${API_BASE:-http://127.0.0.1:8090/api/v1}; S=$(date +%s); P="Api-pass-$S"
j() { python3 -c "import sys,json; d=json.load(sys.stdin); print(eval(sys.argv[1]))" "$1"; }
pass=0; fail=0; ok() { if [ "$1" = "$2" ]; then pass=$((pass+1)); echo "PASS $3"; else fail=$((fail+1)); echo "FAIL $3 (got '$1', want '$2')"; fi; }
reg() { curl -s -X POST $B/auth/register -H 'Content-Type: application/json' -d "{\"name\":\"Api $1\",\"email\":\"api-$1-$S@example.test\",\"password\":\"$P\",\"accept_terms\":true}" | j "d['data']['token']"; }
A=$(reg a); Bt=$(reg b)
for t in $A $Bt; do curl -s -X POST $B/onboarding -H "Authorization: Bearer $t" -H 'Content-Type: application/json' -d '{"native_lang":"en","target_lang":"es","cefr_level":"A1"}' >/dev/null; done
H="Authorization: Bearer $A"; J='Content-Type: application/json'
L=$(curl -s "$B/lists?lang=es" -H "$H"); ok "$(echo $L | j "len(d['data']['lists'])")" "1" "GET lists has Saved"
SAVED=$(echo $L | j "d['data']['lists'][0]['id']")
C=$(curl -s -X POST $B/flashcards -H "$H" -H "$J" -d '{"word":"perro","translation":"dog","lang":"es"}'); CID=$(echo $C | j "d['data']['card']['id']")
ok "$(echo $C | j "d['data']['card']['list_ids']")" "$SAVED" "new card lands in Saved"
N=$(curl -s -X POST $B/lists -H "$H" -H "$J" -d "{\"name\":\"Animals\",\"lang\":\"es\",\"cards\":[$CID]}"); NID=$(echo $N | j "d['data']['list']['id']")
ok "$(echo $N | j "d['data']['list']['cards']")" "1" "create list with a card"
ok "$(curl -s $B/lists/$NID/cards -H "$H" | j "d['data']['cards'][0]['word']")" "perro" "GET list cards"
CARDS=$(curl -s "$B/flashcards?tab=all&lang=es" -H "$H" | j "','.join(str(c['id']) for c in [c for c in d['data']['cards'] if c['id'] != $CID][:3])")
ok "$(curl -s -X POST $B/lists/$NID/cards -H "$H" -H "$J" -d "{\"cards\":[$CARDS]}" | j "d['data']['list']['cards']")" "4" "add 3 starter cards"
ok "$(curl -s -X POST $B/lists/$NID/cards -H "$H" -H "$J" -d "{\"cards\":[$CID],\"from\":$SAVED}" | j "d['data']['list']['cards']")" "4" "move perro out of Saved"
ok "$(curl -s "$B/lists?lang=es" -H "$H" | j "d['data']['lists'][0]['cards']")" "0" "Saved now empty"
ok "$(curl -s -X POST $B/lists/$NID/cards/remove -H "$H" -H "$J" -d "{\"cards\":[$CID]}" | j "d['data']['list']['cards']")" "3" "remove from list"
ok "$(curl -s $B/flashcards/$CID -H "$H" | j "d['data']['card']['word']")" "perro" "card survives removal"
ok "$(curl -s -X PATCH $B/lists/$NID -H "$H" -H "$J" -d '{"name":"Pets"}' | j "d['data']['list']['name']")" "Pets" "rename"
ok "$(curl -s -o /dev/null -w '%{http_code}' -X PATCH $B/lists/$SAVED -H "$H" -H "$J" -d '{"name":"x"}')" "409" "Saved can't be renamed (409)"
ok "$(curl -s -o /dev/null -w '%{http_code}' -X POST $B/lists -H "$H" -H "$J" -d '{"name":"  "}')" "422" "empty name (422)"
HB="Authorization: Bearer $Bt"
for req in "GET lists/$NID/cards" "PATCH lists/$NID" "DELETE lists/$NID" "POST lists/$NID/cards" "POST lists/$NID/cards/remove"; do set -- $req
  ok "$(curl -s -o /dev/null -w '%{http_code}' -X $1 $B/$2 -H "$HB" -H "$J" -d "{\"name\":\"x\",\"cards\":[$CID]}")" "404" "IDOR blocked: $req"; done
MB=$(curl -s -X POST $B/lists -H "$HB" -H "$J" -d '{"name":"Mine"}' | j "d['data']['list']['id']")
ok "$(curl -s -o /dev/null -w '%{http_code}' -X POST $B/lists/$MB/cards -H "$HB" -H "$J" -d "{\"cards\":[$CID]}")" "404" "IDOR blocked: foreign card into own list"
ok "$(curl -s -o /dev/null -w '%{http_code}' $B/lists)" "401" "no token (401)"
EX=$(curl -s $B/account/export -H "$H" | j "len(d['data']['card_lists']) if 'data' in d else len(d['card_lists'])" 2>/dev/null); ok "$EX" "2" "account export has lists"
ok "$(curl -s -X DELETE $B/lists/$NID -H "$H" | j "d['data']['deleted']")" "True" "delete list"
ok "$(curl -s $B/flashcards/$CID -H "$H" | j "d['data']['card']['word']")" "perro" "card survives list delete"
echo "TOTAL PASS $pass FAIL $fail"
