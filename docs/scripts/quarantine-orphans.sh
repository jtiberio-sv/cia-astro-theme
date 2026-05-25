#!/bin/bash
# Move arquivos orfaos identificados em /tmp/cdm-orphans-list.txt pra quarantine.
# Preserva estrutura year/month/file pra rollback trivial.
# Acompanha .webp/.avif/-150x150-thumbs gerados pelos orfaos.
set -uo pipefail

UPLOADS_DIR="/var/www/ciadasmochilas.com.br/htdocs/wp-content/uploads"
QUARANTINE="/var/quarantine/cdm-uploads-2026-05-22"
LIST="/tmp/cdm-orphans-list.txt"
LOG="/tmp/cdm-quarantine-2026-05-22.log"

if [[ ! -f "$LIST" ]]; then
  echo "ERROR: list $LIST nao existe (rode scan-orphans-v2.php primeiro)"
  exit 1
fi

echo "=== Quarantine $(date) ===" | tee "$LOG"
echo "Source: $UPLOADS_DIR" | tee -a "$LOG"
echo "Dest:   $QUARANTINE" | tee -a "$LOG"
echo "List:   $LIST ($(wc -l < $LIST) entries)" | tee -a "$LOG"

sudo mkdir -p "$QUARANTINE"
sudo chown www-data:www-data "$QUARANTINE"

count=0
size_total=0
errors=0
companions=0

while IFS= read -r path; do
  [[ -z "$path" ]] && continue
  if [[ ! -f "$path" ]]; then
    continue
  fi

  rel="${path#$UPLOADS_DIR/}"
  dest="$QUARANTINE/$rel"
  dest_dir=$(dirname "$dest")

  sudo mkdir -p "$dest_dir"
  if ! sudo mv "$path" "$dest" 2>>"$LOG"; then
    errors=$((errors+1))
    continue
  fi
  count=$((count+1))

  # Move companions: .webp, .avif (gerados sem dot extra) E thumbs WP -<W>x<H>.ext
  # Companions ficam na mesma pasta original, com basename derivado
  base_no_ext="${path%.*}"
  ext="${path##*.}"

  for suffix in "${path}.webp" "${path}.avif"; do
    if [[ -f "$suffix" ]]; then
      sufrel="${suffix#$UPLOADS_DIR/}"
      sudo mv "$suffix" "$QUARANTINE/$sufrel" 2>/dev/null && companions=$((companions+1))
    fi
  done
done < "$LIST"

echo "" | tee -a "$LOG"
echo "=== Resultado ===" | tee -a "$LOG"
echo "Movidos:    $count arquivos principais" | tee -a "$LOG"
echo "Companions: $companions (.webp/.avif)" | tee -a "$LOG"
echo "Erros:      $errors" | tee -a "$LOG"
sudo du -sh "$QUARANTINE" 2>&1 | tee -a "$LOG"
echo "" | tee -a "$LOG"

echo "=== Disk uploads APOS quarantine ===" | tee -a "$LOG"
sudo du -sh "$UPLOADS_DIR"/20*/ 2>&1 | sort | tee -a "$LOG"
