import time

sessions = {}

WARNING_TIME  = 3   # ثواني قبل الـ warning
KILL_TIME     = 5   # ثواني قبل الـ terminate
MAX_WARNINGS  = 2   # أقصى عدد warnings


def update_session(session_id: str, is_cheating: bool, cheat_reason: str = None) -> dict:
    now = time.time()

    # ── إنشاء Session جديد لو مش موجود ───────────────────────────────────────
    if session_id not in sessions:
        sessions[session_id] = {
            "last_ok"      : now,
            "warnings"     : 0,
            "status"       : "OK",
            "cheat_reason" : None,
            "cheat_log"    : []     # سجل كل أحداث الغش
        }

    s = sessions[session_id]

    # ── الطالب تمام → reset ───────────────────────────────────────────────────
    if not is_cheating:
        s["last_ok"]      = now
        s["status"]       = "OK"
        s["cheat_reason"] = None
        return s

    # ── الطالب بيغش → حساب المدة ─────────────────────────────────────────────
    absent_time = now - s["last_ok"]
    s["cheat_reason"] = cheat_reason

    # سجل الحدث
    s["cheat_log"].append({
        "time"   : round(now, 2),
        "reason" : cheat_reason,
        "duration": round(absent_time, 2)
    })

    # ── Terminate (البق اللي كان موجود اتصلح هنا بـ elif) ────────────────────
    if absent_time >= KILL_TIME:
        s["status"] = "TERMINATED"

    # ── Warning ───────────────────────────────────────────────────────────────
    elif absent_time >= WARNING_TIME:
        s["warnings"] += 1
        s["last_ok"]   = now  # reset العداد بعد الـ warning
        s["status"]    = f"WARNING_{s['warnings']}"

        if s["warnings"] >= MAX_WARNINGS:
            s["status"] = "TERMINATED"

    # ── لسه في الـ grace period ───────────────────────────────────────────────
    else:
        s["status"] = "WATCHING"  # بيغش بس لسه في الـ grace period

    return s
