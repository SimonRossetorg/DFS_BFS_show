# DFS_BFS_show

Dieses Projekt enthält nun eine leichte PHP REST-API, um Tiefen- und Breitensuche auf einem gerichteten Graphen bereitzustellen.

## Projektstruktur

```
api/
├── public/
│   └── index.php          # Einstiegspunkt für den PHP-Server
└── src/
    ├── Controllers/       # REST-Controller (Graph- & Health-Endpunkte)
    ├── Http/              # Request- und Response-Hilfsklassen
    ├── Services/          # Fachliche Logik für DFS/BFS
    └── bootstrap.php      # Einfache Autoloader-Registrierung
```

## Lokalen Server starten

Stellen Sie sicher, dass PHP installiert ist, und starten Sie anschließend den eingebauten Webserver:

```bash
php -S 0.0.0.0:8000 -t api/public
```

Die API ist danach unter `http://localhost:8000` erreichbar.

## Endpunkte

### `GET /health`

Gibt einen einfachen Heartbeat mit Zeitstempel zurück.

**Beispielantwort**
```json
{
  "status": "ok",
  "timestamp": "2023-01-01T12:00:00+00:00"
}
```

### `POST /graph/traverse/dfs`
Führt eine Tiefensuche (Depth First Search) zwischen zwei Knoten aus.

### `POST /graph/traverse/bfs`
Führt eine Breitensuche (Breadth First Search) zwischen zwei Knoten aus.

#### Gemeinsames Request-Format

```json
{
  "graph": {
    "A": ["B", "C"],
    "B": ["D"],
    "C": ["D"],
    "D": []
  },
  "start": "A",
  "target": "D"
}
```

#### Beispiel: DFS anfragen

```bash
curl -X POST http://localhost:8000/graph/traverse/dfs \
  -H "Content-Type: application/json" \
  -d '{
    "graph": {
      "A": ["B", "C"],
      "B": ["D"],
      "C": ["D"],
      "D": []
    },
    "start": "A",
    "target": "D"
  }'
```

**Beispielantwort**
```json
{
  "path": ["A", "B", "D"],
  "visited": ["A", "B", "D"],
  "algorithm": "dfs",
  "found": true
}
```

Die BFS-Variante (`/graph/traverse/bfs`) besitzt dasselbe Request-Format und liefert den Weg gemäß Breitensuche zurück.

### Live-Verfolgung der Suchen

Um die Schritte der Algorithmen mitzulesen, stehen Streaming-Endpunkte bereit. Sie akzeptieren das identische Request-Format, antworten jedoch als Server-Sent-Events (SSE).

#### `POST /graph/traverse/dfs/stream`
#### `POST /graph/traverse/bfs/stream`

Jedes `step`-Event enthält den aktuell besuchten Knoten, die bisherige Besuchsreihenfolge sowie den aktuellen Frontier-Zustand (Stack bzw. Queue). Abschließend folgt ein `complete`-Event mit dem Gesamtergebnis. Bei ungültigen Eingaben wird ein `error`-Event gesendet.

**Beispielaufruf (DFS streamen)**

```bash
curl -N -X POST http://localhost:8000/graph/traverse/dfs/stream \
  -H "Content-Type: application/json" \
  -d '{
    "graph": {
      "A": ["B", "C"],
      "B": ["D"],
      "C": ["D"],
      "D": []
    },
    "start": "A",
    "target": "D"
  }'
```

**Auszug aus dem Stream**

```
event: step
data: {"algorithm":"dfs","step":1,"current":"A","path":["A"],"visited":["A"],"frontierType":"stack","frontier":[{"node":"C","path":["A","C"]},{"node":"B","path":["A","B"]}],"found":false,"skipped":false}

event: complete
data: {"path":["A","B","D"],"visited":["A","B","D"],"algorithm":"dfs","found":true,"steps":3}
```
