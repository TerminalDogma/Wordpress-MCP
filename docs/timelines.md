# Story timelines

Pull the latest checkout and run the following with Docker Desktop running.

```sh
git pull --ff-only
sh bin/shared-content.sh timeline
```

On the default Mac installation, open **Timelines → MCU In-Universe Timeline** in the catalog navigation on [Site A](http://localhost:8080/site-a/timelines/mcu-in-universe/) or [Site B](http://localhost:8080/site-b/timelines/mcu-in-universe/). This also runs during a full setup. The update connects the example to the **Marvel Cinematic Universe** franchise and matches existing catalog records where the match is unambiguous. It adds six starter titles where needed. The command retains existing entries and their publication states. You can change participation in **Network Admin → Shared Content → Sites and Content Types**.

To author a timeline, go to **Shared Content → Timelines → Create global record**. Add a title and an introduction. Optionally select a **Related franchise** so that its published page links to the chronology.

1. Add and name up to six columns. Use **Move left** or **Move right** to change their display order. Create section headings separately in the **Sections** list.
2. Add eras or phases and choose their colors. Those labels appear in the public key.
3. Add entries in story order and choose a column and section from their dropdowns. Enter an exact date, approximate period or relative label, or choose Undated.
4. Search the catalog for the record you want to link. The card uses the linked record's poster, headshot, logo or featured image automatically. Choose a custom Media Library image if the card needs different artwork.
5. Save the global record and publish it from the Timelines post list or the ACF publication controls. Published catalog entries linked to the timeline show it in their **Story timelines** section. Readers can filter by column and era.

For movie cast cards, edit **Cast characters** in Shared Content or ACF. Enter one linked actor and character per line, for example `Robert Downey Jr. | Iron Man`. The character appears beneath the actor's name. Wikidata imports populate this field when the source supplies character credits.

The 56-entry MCU example follows selected main stories and separates branch timelines and the TVA. Phase color identifies when a production belongs in the MCU slate. The dates and placement describe when its story happens. The sample uses release-phase and chronological guidance from [Disney+](https://www.disneyplus.com/explore/articles/marvel-movies-in-order) and [Marvel](https://www.marvel.com/articles/movies/mcu-timeline-order-disney-plus).
