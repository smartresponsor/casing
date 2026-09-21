#!/bin/sh
# dot.sh
# Dot-run scanner + runner (ps/sh) with fallback + logging.
# Entry: dot_folder "."  (auto-called at the bottom if executed directly)

dot_folder() {
  ROOT="${1:-.}"

  # Repo-root autodetect (canon):
  # Priority:
  #   1) DOT_ROOT env (explicit)
  #   2) explicit arg ROOT (if not ".")
  #   3) closest ancestor that CONTAINS ".commanding" folder (most reliable)
  # Fallback:
  #   4) git toplevel (if available)
  #   5) closest repo marker: .git OR (MANIFEST.json + .gitignore)
  dot_script_dir() {
    CDPATH= cd -- "$(dirname -- "$0")" 2>/dev/null && pwd
  }

  dot_has_commanding() {
    d="$1"
    [ -d "$d/.commanding" ]
  }

  dot_is_repo_marker() {
    d="$1"
    [ -d "$d/.git" ] || [ -f "$d/.git" ] || ( [ -f "$d/MANIFEST.json" ] && [ -f "$d/.gitignore" ] )
  }

  dot_find_root_up_by_commanding() {
    start="$1"
    d="$start"
    while :; do
      if dot_has_commanding "$d"; then
        bn="$(basename "$d")"
        case "$bn" in
          .*) : ;; # ignore commanding inside dot-folders (e.g., .gating/.commanding)
          *) printf "%s
" "$d"; return 0 ;;
        esac
      fi
      parent="$(cd -- "$d/.." 2>/dev/null && pwd)"
      [ "$parent" = "$d" ] && break
      d="$parent"
    done
    return 1
  }
  dot_find_root_up_by_gate() {
    start="$1"
    d="$start"
    while :; do
      if [ -f "$d/.gating/gate.sh" ]; then
        printf "%s
" "$d"
        return 0
      fi
      parent="$(cd -- "$d/.." 2>/dev/null && pwd)"
      [ "$parent" = "$d" ] && break
      d="$parent"
    done
    return 1
  }

  dot_find_root_up_by_marker() {
    start="$1"
    d="$start"
    while :; do
      if dot_is_repo_marker "$d"; then
        printf "%s
" "$d"
        return 0
      fi
      parent="$(cd -- "$d/.." 2>/dev/null && pwd)"
      [ "$parent" = "$d" ] && break
      d="$parent"
    done
    return 1
  }

  dot_pick_root() {
    # 1) DOT_ROOT override
    if [ -n "${DOT_ROOT:-}" ] && [ -d "${DOT_ROOT:-}" ]; then
      printf "%s
" "$DOT_ROOT"
      return 0
    fi

    # 2) explicit arg (if not ".")
    if [ -n "${ROOT:-}" ] && [ "$ROOT" != "." ] && [ -d "$ROOT" ]; then
      printf "%s
" "$ROOT"
      return 0
    fi

    # 3) prefer folder containing ".commanding"
    sd="$(dot_script_dir)"
    rr_gate="$(dot_find_root_up_by_gate "$sd" 2>/dev/null || true)"
    if [ -n "$rr_gate" ] && [ -d "$rr_gate" ]; then
      printf "%s
" "$rr_gate"
      return 0
    fi

    rr="$(dot_find_root_up_by_commanding "$sd" 2>/dev/null || true)"
    if [ -n "$rr" ] && [ -d "$rr" ]; then
      printf "%s
" "$rr"
      return 0
    fi

    # 4) git toplevel from script dir (if git exists)
    if command -v git >/dev/null 2>&1; then
      tl="$(cd "$sd" 2>/dev/null && git rev-parse --show-toplevel 2>/dev/null || true)"
      if [ -n "$tl" ] && [ -d "$tl" ]; then
        printf "%s
" "$tl"
        return 0
      fi
    fi

    # 5) repo marker fallback
    rr2="$(dot_find_root_up_by_marker "$sd" 2>/dev/null || true)"
    if [ -n "$rr2" ] && [ -d "$rr2" ]; then
      printf "%s
" "$rr2"
      return 0
    fi

    pwd
  }

  ROOT="$(dot_pick_root)"
  cd "$ROOT" 2>/dev/null || true
  ROOT="$(pwd)"


  # Accept list (whitelist): ONLY these dot-folders will be processed

  # DOT_ACCEPT is loaded from YAML (preferred) or env fallback.
  # Format (YAML):
  #   accept:
  #     - .smoke
  #     - .gating
  # Comments (#) and empty lines are ignored.
  DOT_ACCEPT_FILE="${DOT_ACCEPT_FILE:-$ROOT/.commanding/policy/dot-accept.yaml}"
  DOT_IGNORE_FILE="${DOT_IGNORE_FILE:-$ROOT/.commanding/policy/dot-ignore.yaml}"

  dot_load_ignore_from_yaml() {
    f="$1"
    [ -f "$f" ] || return 1
    sed 's/\r$//' "$f" 2>/dev/null | \
      sed 's/#.*$//' | \
      sed 's/^[[:space:]]*-[[:space:]]*//p;d' | \
      sed 's/[[:space:]]*$//' | \
      sed '/^\./!d' | \
      sed '/^$/d'
  }

  # Always ignore these dot-folders (repo metadata / IDE):
  DOT_IGNORE_DEFAULT="
.git
.github
.idea
"

  DOT_IGNORE="$(printf "%s\n" "$DOT_IGNORE_DEFAULT")"
  EXTRA_IGNORE="$(dot_load_ignore_from_yaml "$DOT_IGNORE_FILE" 2>/dev/null || true)"
  if [ -n "$EXTRA_IGNORE" ]; then
    DOT_IGNORE="$(printf "%s\n%s\n" "$DOT_IGNORE" "$EXTRA_IGNORE")"
  fi

  dot_is_ignored_dir() {
    d="$1"
    b="$(basename "$d")"
    # ignore list contains names like ".git"
    printf "%s\n" "$DOT_IGNORE" | sed 's/^ *//g' | sed '/^$/d' | grep -Fxq "$b"
  }

  dot_menu_label() {
    d="$1"
    b="$(basename "$d")"
    # default: show basename only; set DOT_MENU_PATH=full for full path
    if [ "${DOT_MENU_PATH:-base}" = "full" ]; then
      printf "%s" "$d"
    else
      printf "%s" "$b"
    fi
  }


  dot_load_accept_from_yaml() {
    f="$1"
    [ -f "$f" ] || return 1
    # Extract list items "- <value>" ignoring comments.
    # Keep only entries that start with a dot.
    sed 's/\r$//' "$f" 2>/dev/null |       sed 's/#.*$//' |       sed 's/^[[:space:]]*-[[:space:]]*//p;d' |       sed 's/[[:space:]]*$//' |       sed '/^\./!d' |       sed '/^$/d'
  }

  if [ -z "${DOT_ACCEPT:-}" ]; then
    # Load from YAML if available.
    DOT_ACCEPT="$(dot_load_accept_from_yaml "$DOT_ACCEPT_FILE" 2>/dev/null || true)"
  fi

  if [ -z "${DOT_ACCEPT:-}" ]; then
    # Built-in fallback (keeps dot working even if YAML is absent).
    DOT_ACCEPT="
.smoke
.gating
.release
.tool
"
  fi



  DOT_LIST="${DOT_LIST:-1}"                 # 1: print accepted dot-folders with status
  DOT_VERBOSE_LIST="${DOT_VERBOSE_LIST:-0}" # 1: also print non-accepted dot-folders
  DOT_STRICT="${DOT_STRICT:-0}"             # 1: fail if accepted folder is NONRUN
  DOT_NONINTERACTIVE="${DOT_NONINTERACTIVE:-1}" # 1: export DOT_AUTO=1 and pass --dot-auto
  DOT_ANSI="${DOT_ANSI:-0}"                 # 1: use ANSI strike-through (if supported)

  dot_have_cmd() { command -v "$1" >/dev/null 2>&1; }

  dot_is_windows() {
    [ "${OS:-}" = "Windows_NT" ] && return 0
    uname_s="$(uname 2>/dev/null || echo "")"
    echo "$uname_s" | grep -qiE 'mingw|msys|cygwin' && return 0
    return 1
  }

  dot_ps_runner() {
    if dot_have_cmd pwsh; then echo "pwsh"; return 0; fi
    if dot_have_cmd powershell; then echo "powershell"; return 0; fi
    if dot_have_cmd powershell.exe; then echo "powershell.exe"; return 0; fi
    return 1
  }

  dot_is_accepted_dir() {
    _base="$(basename "$1")"
    [ "$_base" = ".commanding" ] && return 1
    for _a in $DOT_ACCEPT; do
      [ "$_base" = "$_a" ] && return 0
    done
    return 1
  }

  dot_now() {
    date +"%Y%m%d-%H%M%S" 2>/dev/null || echo "time-unknown"
  }

  dot_log_init() {
    [ -n "${DOT_LOG:-}" ] && return 0
    ts="$(dot_now)"

    if [ -d "./.commanding" ]; then
      mkdir -p "./.commanding/log" 2>/dev/null || true
      DOT_LOG="./.commanding/log/dot-$ts.log"
    else
      DOT_LOG="/tmp/dot-$ts.log"
    fi

    : > "$DOT_LOG" 2>/dev/null || DOT_LOG="/tmp/dot-$ts.log"
    export DOT_LOG
  }

  dot_log() {
    dot_log_init
    printf "%s\n" "$*" >> "$DOT_LOG"
  }

  dot_detect_runner_for_runfile() {
    f="$1"
    first="$(head -n 1 "$f" 2>/dev/null || true)"

    echo "$first" | grep -qiE 'pwsh|powershell' && { echo "ps"; return 0; }
    echo "$first" | grep -qiE 'sh|bash' && { echo "sh"; return 0; }

    if dot_is_windows && dot_ps_runner >/dev/null 2>&1; then
      echo "ps"; return 0
    fi
    echo "sh"
  }

  dot_pick_candidates() {
    # output lines: "<runner>\t<file>"
    d="$1"

    ps=""
    sh=""
    any=""

    [ -f "$d/run.ps1" ] && ps="$d/run.ps1"
    [ -f "$d/run.sh" ]  && sh="$d/run.sh"
    [ -f "$d/run" ]     && any="$d/run"

    # explicit pair: prefer by platform
    if [ -n "$ps" ] && [ -n "$sh" ]; then
      if dot_is_windows && dot_ps_runner >/dev/null 2>&1; then
        printf "ps\t%s\n" "$ps"
        printf "sh\t%s\n" "$sh"
        return 0
      fi
      printf "sh\t%s\n" "$sh"
      printf "ps\t%s\n" "$ps"
      return 0
    fi

    # single explicit
    [ -n "$ps" ] && printf "ps\t%s\n" "$ps"
    [ -n "$sh" ] && printf "sh\t%s\n" "$sh"

    # generic run => detect
    if [ -n "$any" ]; then
      r="$(dot_detect_runner_for_runfile "$any")"
      printf "%s\t%s\n" "$r" "$any"
    fi

    return 0
  }

  dot_exec_one_logged() {
    runner="$1"
    file="$2"
    shift 2 || true
    extra_args="$*"
    dir="$(dirname "$file")"
    base="$(basename "$file")"

    dot_log_init

    dot_log "============================================================"
    dot_log "START  runner=$runner  file=$file"
    dot_log "CWD    $dir"
    dot_log "TIME   $(dot_now)"
    dot_log "------------------------------------------------------------"

    tmp_out="$(mktemp)"
    trap 'rm -f "$tmp_out"' INT TERM HUP

    (
      cd "$dir" || exit 1

      if [ "${DOT_NONINTERACTIVE:-1}" = "1" ]; then
        export DOT_AUTO=1
      fi

      if [ "$runner" = "ps" ]; then
        psbin="$(dot_ps_runner 2>/dev/null || true)"
        [ -n "$psbin" ] || { echo "PowerShell runner not found"; exit 127; }
        "$psbin" -NoProfile -ExecutionPolicy Bypass -File "./$base"
      else
        if [ -x "./$base" ]; then
          "./$base" --dot-auto
        else
          sh "./$base" --dot-auto
        fi
      fi
    ) >"$tmp_out" 2>&1

    rc=$?

    # show live output
    cat "$tmp_out"
    # append to log
    cat "$tmp_out" >> "$DOT_LOG"

    dot_log "------------------------------------------------------------"
    dot_log "END    runner=$runner  rc=$rc"
    dot_log "TIME   $(dot_now)"
    dot_log "============================================================"
    dot_log ""

    rm -f "$tmp_out"
    trap - INT TERM HUP

    return "$rc"
  }

  dot_exec_with_fallback() {
    d="$1"

    tmp="$(mktemp)"
    trap 'rm -f "$tmp"' INT TERM HUP
    dot_pick_candidates "$d" > "$tmp"

    [ -s "$tmp" ] || { rm -f "$tmp"; trap - INT TERM HUP; return 0; }

    primary_runner=""
    primary_file=""
    fallback_runner=""
    fallback_file=""

    i=0
    while IFS="$(printf '\t')" read -r runner file; do
      [ -n "$runner" ] || continue
      [ -n "$file" ] || continue
      i=$((i + 1))
      if [ "$i" -eq 1 ]; then
        primary_runner="$runner"
        primary_file="$file"
      elif [ "$i" -eq 2 ]; then
        fallback_runner="$runner"
        fallback_file="$file"
        break
      fi
    done < "$tmp"

    rm -f "$tmp"
    trap - INT TERM HUP

    dot_log_init
    dot_log "DOT-FOLDER: $d"
    dot_log "PRIMARY : $primary_runner -> $primary_file"
    [ -n "$fallback_file" ] && dot_log "FALLBACK: $fallback_runner -> $fallback_file"
    dot_log ""

    echo ""
    echo "TRY(primary): $primary_runner -> $primary_file"
    echo ""

    if dot_exec_one_logged "$primary_runner" "$primary_file"; then
      echo ""
      echo "OK(primary): $primary_runner"
      echo "LOG: $DOT_LOG"
      echo ""
      return 0
    fi
    rc1=$?

    if [ -n "$fallback_file" ] && [ "$fallback_file" != "$primary_file" ]; then
      echo ""
      echo "TRY(fallback): $fallback_runner -> $fallback_file"
      echo ""

      if dot_exec_one_logged "$fallback_runner" "$fallback_file"; then
        echo ""
        echo "OK(fallback): $fallback_runner (primary rc=$rc1)"
        echo "LOG: $DOT_LOG"
        echo ""
        return 0
      fi
      rc2=$?
    else
      rc2=0
    fi

    echo ""
    echo "FAILED: primary rc=$rc1, fallback rc=$rc2"
    echo "LOG: $DOT_LOG"
    echo ""

    if [ -n "$fallback_file" ]; then
      return "$rc2"
    fi
    return "$rc1"
  }

  dot_scan() {
    find "$ROOT" -type d -name '.*' -print 2>/dev/null || true
  }

  dot_has_any_run() {
    d="$1"
    [ -f "$d/run.ps1" ] || [ -f "$d/run.sh" ] || [ -f "$d/run" ]
  }

  # --- BOOT: scan and execute -------------------------------------------------

  echo ""
  echo "Dot boot: scanning $ROOT ..."
  echo ""

  dot_log_init
  dot_log "DOT root=$ROOT time=$(dot_now)"

  found_accept=0
  found_run=0
  found_nonrun=0

  scan_list="$(dot_scan 2>/dev/null || true)"

  IDX=0
  ITEMS=""       # <idx>|<status>|<dir>
  RUN_COUNT=0
  NONRUN_COUNT=0
  ACCEPT_COUNT=0

  while IFS= read -r d; do
    [ -n "$d" ] || continue
    base="$(basename "$d")"

    # Always skip .commanding itself
    if [ "$base" = ".commanding" ]; then
      continue
    fi

    # Ignore repo/IDE dot-folders (policy)
    if dot_is_ignored_dir "$d"; then
      continue
    fi

    if dot_is_accepted_dir "$d"; then
      ACCEPT_COUNT=$((ACCEPT_COUNT + 1))
      if dot_has_any_run "$d"; then
        status="RUN"
        RUN_COUNT=$((RUN_COUNT + 1))
      else
        status="NONRUN"
        NONRUN_COUNT=$((NONRUN_COUNT + 1))
      fi
    else
      status="SKIP"
    fi

    IDX=$((IDX + 1))
    ITEMS="${ITEMS}${IDX}|${status}|${d}
"
  done <<EOF
$scan_list
EOF

  # Optional: print discovered folders (debug)
  if [ "${DOT_PRINT_LIST:-0}" = "1" ] && [ -n "$ITEMS" ]; then
    echo ""
    printf "%s" "$ITEMS" | while IFS='|' read -r n st dir; do
      [ -n "$n" ] || continue
      if [ "$st" = "RUN" ]; then
        echo "[dot] RUN      $dir"
      elif [ "$st" = "NONRUN" ]; then
        if [ "${DOT_ANSI:-0}" = "1" ] && [ -t 1 ] 2>/dev/null; then
          printf '[dot] NONRUN   \033[9m%s\033[0m (missing run.*)\n' "$dir"
        else
          echo "[dot] NONRUN   $dir (missing run.*)"
        fi
      else
        echo "[dot] SKIP     $dir"
      fi
    done
    echo ""
  fi

  echo "[dot] summary: accepted=$ACCEPT_COUNT runnable=$RUN_COUNT nonrun=$NONRUN_COUNT"

  # Strict mode: accepted but missing run.* => fail
  if [ "${DOT_STRICT:-0}" = "1" ] && [ "$NONRUN_COUNT" -gt 0 ]; then
    echo ""
    echo "[dot] STRICT: accepted folders missing run.* ($NONRUN_COUNT)"
    echo ""
    exit 3
  fi

  # Decide interactive vs auto-run
  DOT_MENU="${DOT_MENU:-1}"
  AUTO_FLAG="0"
  if [ "${DOT_AUTO:-0}" = "1" ]; then AUTO_FLAG="1"; fi
  for a in "$@"; do
    [ "$a" = "--dot-auto" ] && AUTO_FLAG="1"
  done

  if [ "$DOT_MENU" = "1" ] && [ "$AUTO_FLAG" = "0" ] && [ -t 1 ] 2>/dev/null; then
    # Menu mode
    if [ "$RUN_COUNT" -eq 0 ]; then
      echo ""
      echo "Nothing runnable found."
      echo "DOT_ACCEPT:"
      printf "%s\n" "$DOT_ACCEPT" | sed 's/^ *//g' | sed '/^$/d' | while IFS= read -r x; do
        printf "  - %s\n" "$x"
      done
      echo ""
    else
      echo "Menu:"
  echo "  (only [RUN] is executable; others are informational)"
      printf "%s" "$ITEMS" | while IFS='|' read -r n st dir; do
        [ -n "$n" ] || continue
        if [ "$st" = "RUN" ]; then
          echo "  [$n] [RUN]    $(dot_menu_label "$dir")"
        elif [ "$st" = "NONRUN" ]; then
          echo "  [$n] [NONRUN] $(dot_menu_label "$dir") (missing run.*)"
        else
          echo "  [$n] [SKIP]   $(dot_menu_label "$dir")"
        fi
      done
      echo ""
      echo "Choose: number = run, q = quit"
      printf "> "
      read -r choice || choice="q"
    if [ "$choice" = "q"  ] || [ "$choice" = "Q" ] || [ -z "$choice" ]; then
        :
      else
        target_line="$(printf "%s" "$ITEMS" | awk -F'|' -v c="$choice" '$1==c {print $0}')"
        if [ -z "$target_line" ]; then
          echo "[dot] unknown selection: $choice"
        else
          sel_status="$(printf "%s" "$target_line" | awk -F'|' '{print $2}')"
          sel_dir="$(printf "%s" "$target_line" | awk -F'|' '{print $3}')"
          if [ "$sel_status" != "RUN" ]; then
            echo "[dot] $sel_status: $(dot_menu_label "$sel_dir")"
          else
            export DOT_AUTO=1
            echo "==> $sel_dir"
            dot_exec_with_fallback "$sel_dir" || true
          fi
        fi
      fi
    fi
  else
    # Auto-run mode
    if [ "$RUN_COUNT" -eq 0 ]; then
      echo ""
      echo "Nothing found."
      echo "DOT_ACCEPT:"
      printf "%s\n" "$DOT_ACCEPT" | sed 's/^ *//g' | sed '/^$/d' | while IFS= read -r x; do
        printf "  - %s\n" "$x"
      done
      echo ""
    else
      export DOT_AUTO=1
      printf "%s" "$ITEMS" | while IFS='|' read -r n st dir; do
        [ -n "$n" ] || continue
        [ "$st" = "RUN" ] || continue
        echo "==> $dir"
        dot_exec_with_fallback "$dir" || true
      done
    fi
  fi

}

# Run if executed directly (Commanding runs this file as a script)
dot_folder "${1:-.}"
