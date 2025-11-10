import csv, json, sys
src = "data.csv"         # <-- your CSV path
dst = "train.jsonl"

with open(src, newline='', encoding="utf-8") as f, open(dst, "w", encoding="utf-8") as out:
    r = csv.DictReader(f)
    for row in r:
        context = (row.get("Context") or "").strip()
        response = (row.get("Response") or "").strip()
        if not context or not response:
            continue
        obj = {
            "messages": [
                {"role": "user", "content": context},
                {"role": "assistant", "content": response}
            ]
        }
        out.write(json.dumps(obj, ensure_ascii=False) + "\n")

print("Wrote", dst)
