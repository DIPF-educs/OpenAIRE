import json
import sys

def extract(filename):
    with open(filename, 'r') as f:
        data = [json.loads(x) for x in f.readlines()]
        #gives an array of requests and token_auth
        #as requests vary due to network latency and processing power
        reqs = [r for x in data for r in x['requests']]
        return reqs

print("Loading jsons")
gold = extract('gold.json')
test = extract('test.json')

# compare those
print("Checking length")
assert len(gold) == len(test)
print("Sorting entries")
#sort by cdt > url > cip > ua > bw_bytes || 0
gold.sort(key = lambda x: (x['cdt'], x['url'], x['cip'], x['ua'], x['bw_bytes'] if 'bw_bytes' in x else 0))
test.sort(key = lambda x: (x['cdt'], x['url'], x['cip'], x['ua'], x['bw_bytes'] if 'bw_bytes' in x else 0))
for (x,y) in zip(gold, test):
    if not x == y:
        print(x)
        print(y)
        sys.exit(1)
print("Entries match")