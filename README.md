This is a "proof of concept" work in progress.

It is aimed at providing me a better interface on working
on the TYPO3 core and gaining insight over open patches.


# TODO

- Vote-Zustand darstellen
- Kommentare inlinen (CI ignore)
- Action links: "Seen this", "Want this", "Abandonable" (-> userservice)
- Sortierung (JS, Userprefs) - hoch|runter sortierung

## Medium Prio

- HTTP-Auth
- Differenzierung Core-Merger / None-Merger / Regulars (Farbe?)
  - Filter:
    - Only new
    - Only prioritized
    - Only Voted -1
    - Only Voted +1
    - Only merge-ready
    - Sort by priority
    - Sort by Last Change
    - Find by owner: [______]
    - Restrict by <BUGFIX|DOCS|TASK|FEATURE|WIP>

## Low Prio
- Unterscheidung DOCS / TASK / BUGFIX / FEATURE / WIP
- Breaking Change (!!!) auswerten
- Forge Links ("Resolves", "Related") aus commit message extrahieren
