<?php
/** Install original MCU presentation articles as native, editable site posts. */
use MSC\{Database, Franchises, NetworkSites, Schema};

if ( ! current_user_can( 'manage_network_options' ) ) {
    throw new RuntimeException( 'Network administrator permission is required.' );
}

$articles = array(
    'mcu-viewing-orders' => array(
        'MCU Viewing Order: Release Dates and Story Chronology',
        array( 'MCU Timeline', 'Viewing Guide', 'Marvel Studios' ),
        'The franchise page and story timeline answer different questions. Release order starts with the productions audiences saw first, while the timeline starts with the earliest events in the story.',
        'Try comparing Captain America: The First Avenger in both views. It arrived after Iron Man in theaters, yet much of its story takes place decades before Tony Stark builds his first suit.',
    ),
    'iron-man-workshop' => array(
        'Iron Man: How the Workshop Tells Tony Stark’s Story',
        array( 'Iron Man', 'Tony Stark', 'Marvel Studios' ),
        'Tony Stark’s workshop gives the film a way to show decisions in progress. The tools, tests and failed prototypes make the suit feel like something with a history.',
        'The first flight tests work as physical comedy and as a preview of the final action. Both depend on the audience knowing how much room Tony has, where the suit can move and what might break.',
    ),
    'loki-branch-timelines' => array(
        'Loki and the TVA: A Guide to the Branching Timelines',
        array( 'Loki', 'Time Variance Authority', 'MCU Timeline', 'Disney+' ),
        'The Time Variance Authority introduces a setting where a character’s experience of time can differ from the order of events outside the organization.',
        'Follow Loki’s story through the series before trying to assign calendar dates. The franchise timeline can keep the TVA story in its own section and still show where the branch begins.',
    ),
    'first-avenger-period-settings' => array(
        'Captain America: The First Avenger and Its Wartime Setting',
        array( 'Captain America: The First Avenger', 'Steve Rogers', 'Phase One' ),
        'The recruitment scenes establish the scale of Steve Rogers’s ambition before the film gives him the equipment to act on it. Tour posters, transport and uniforms make the wartime setting visible throughout.',
        'Compare those early rooms with the places Steve visits after the experiment. The scenes keep returning to the same question of whom he can help and when he has permission to do it.',
    ),
    'avengers-team-structure' => array(
        'How The Avengers Introduces Its Team',
        array( 'The Avengers', 'Avengers', 'Phase One' ),
        'The first team film uses pairings to establish how its characters work. Brief conversations between two heroes give each person room to react before the whole cast shares a scene.',
        'The final fight makes those interactions pay off with handoffs. The audience can follow who is making a decision, who can carry it out and where the other heroes are.',
    ),
    'winter-soldier-genre' => array(
        'The Winter Soldier as a Political Thriller',
        array( 'Captain America: The Winter Soldier', 'Steve Rogers', 'Phase Two' ),
        'Steve spends much of the film deciding which people and systems he trusts. The story makes that question concrete by controlling who can move through secure spaces and who has access to records.',
        'Watch the change from public settings to corridors, elevators and cars. Tight spaces give the action a clear geography and make it harder for characters to walk away from a confrontation.',
    ),
    'guardians-mixtape' => array(
        'The Guardians of the Galaxy Mixtape and Peter Quill’s Past',
        array( 'Guardians of the Galaxy', 'Peter Quill', 'Phase Two' ),
        'Peter Quill carries music from Earth into places where few objects connect to his childhood. The songs can set the pace of a scene while giving his choices a personal context.',
        'Listen for the difference between songs that characters can hear and songs that accompany the action. That distinction changes whether a track feels like Peter’s choice or a comment on the scene.',
    ),
    'ant-man-scale' => array(
        'How Ant-Man Uses Everyday Objects to Show Scale',
        array( 'Ant-Man', 'Scott Lang', 'Phase Two' ),
        'Ant-Man makes size visible by putting familiar objects in the frame. A toy or a bathtub gives the audience a reference point as soon as Scott’s perspective changes.',
        'The biggest changes in scale often come in pairs. A scene first establishes the threat from Scott’s perspective and then cuts wide to show what someone else in the room would see.',
    ),
    'civil-war-consequences' => array(
        'Captain America: Civil War and the Cost of Team Decisions',
        array( 'Captain America: Civil War', 'Avengers', 'Phase Three' ),
        'The film carries consequences from earlier team outings into a debate over who has the right to authorize their actions. The argument gives familiar heroes reasons to land on opposing sides.',
        'The airport sequence brings the debate into motion. Many of the characters have a personal goal within the fight, which helps separate their decisions from the position of their team.',
    ),
    'black-panther-wakanda' => array(
        'Black Panther: How Wakanda’s Locations Shape the Story',
        array( 'Black Panther', 'Wakanda', 'T’Challa', 'Phase Three' ),
        'Wakanda comes into view through locations that serve a clear purpose. The throne room, laboratory and ceremonial spaces show how different people understand the same country.',
        'Follow where T’Challa goes when he needs advice, equipment or public support. The locations help explain what is at stake in each choice without repeating the political history in dialogue.',
    ),
    'doctor-strange-city' => array(
        'Doctor Strange and the Geography of the Mirror Dimension',
        array( 'Doctor Strange', 'Stephen Strange', 'Phase Three' ),
        'The Mirror Dimension turns familiar streets into moving environments. The film keeps paths and landmarks visible long enough for the characters’ choices to register.',
        'Pay attention to entrances and exits during the city pursuit. Even when buildings change position, the movement still has a clear objective.',
    ),
    'thor-ragnarok-sakaar' => array(
        'Thor: Ragnarok and the Design of Sakaar',
        array( 'Thor: Ragnarok', 'Sakaar', 'Thor', 'Phase Three' ),
        'Sakaar gives Thor an unfamiliar society with its own hierarchy and visual rules. Its public spaces put spectacle on display, and the arena shows who controls access to it.',
        'The costumes and interiors help distinguish powerful residents from people caught up in the system. Thor has to learn those differences quickly to find allies and leave.',
    ),
    'infinity-war-ensemble' => array(
        'How Infinity War Connects Its Separate Storylines',
        array( 'Avengers: Infinity War', 'Avengers', 'Thanos', 'Phase Three' ),
        'Infinity War divides its cast into groups with distinct objectives. That structure allows the film to move between locations without restarting the explanation every time.',
        'One way to follow the crosscutting is to track who knows about each Infinity Stone. The audience often sees more of the whole plan than any single hero does.',
    ),
    'endgame-time-heist' => array(
        'The Avengers: Endgame Time Heist in Story Order',
        array( 'Avengers: Endgame', 'Avengers', 'MCU Timeline', 'Phase Three' ),
        'The time heist sends different groups back to events from earlier films. The release order gives those scenes their original context, and the characters’ return visits add a second point of view.',
        'On a story timeline, the date of a destination and the date a traveler departs can both matter. Grouping the sequence by its travelers keeps their individual goals easier to follow.',
    ),
    'wandavision-sitcoms' => array(
        'How WandaVision Changes Its Sitcom Style Across Episodes',
        array( 'WandaVision', 'Wanda Maximoff', 'Phase Four', 'Disney+' ),
        'WandaVision uses changes in lighting, framing and performance to distinguish its sitcom eras. Those shifts help the audience notice when a setting is becoming less familiar to its characters.',
        'Watch how the series changes the rhythm of domestic scenes. A longer pause or a different camera angle can reveal that a routine is no longer working as expected.',
    ),
    'shang-chi-action' => array(
        'Shang-Chi and the Spatial Design of Its Action Scenes',
        array( 'Shang-Chi and the Legend of the Ten Rings', 'Shang-Chi', 'Phase Four' ),
        'The bus sequence establishes the aisle, windows and changing street conditions before those details become obstacles. The scene gains momentum because each new action has a visible consequence.',
        'Later fights exchange that tight space for open ground. The choreography changes accordingly, giving the characters more ways to move around each other.',
    ),
    'ms-marvel-animation' => array(
        'How Ms. Marvel Uses Animation to Show Kamala’s Point of View',
        array( 'Ms. Marvel', 'Kamala Khan', 'Phase Four', 'Disney+' ),
        'Ms. Marvel uses drawings, messages and animated details to let viewers see how Kamala thinks. These elements connect imagined possibilities with physical places in her daily life.',
        'The effect is especially clear when a familiar room or street becomes part of her storytelling. The location stays recognizable while the illustration adds her interpretation.',
    ),
    'moon-knight-identity' => array(
        'Moon Knight: How Locations and Costumes Track Point of View',
        array( 'Moon Knight', 'Marc Spector', 'Steven Grant', 'Phase Four', 'Disney+' ),
        'Moon Knight often places the audience in a character’s immediate experience. A change in location or costume can clarify a shift in perspective before the characters discuss it.',
        'Compare the early museum and apartment scenes with the later sequences overseas. Each setting reveals which routines are familiar and which questions a character still cannot answer.',
    ),
    'wakanda-forever-leadership' => array(
        'How Wakanda Forever Frames Shuri’s Decisions',
        array( 'Black Panther: Wakanda Forever', 'Shuri', 'Wakanda', 'Phase Four' ),
        'Shuri faces choices through several roles at once, including scientist, family member and public figure. The film changes settings to show what each role asks of her.',
        'The lab and ceremonial scenes establish different ways of responding to loss. When those priorities come together, the consequences extend beyond Shuri and her immediate family.',
    ),
    'marvels-team-movement' => array(
        'The Marvels and the Choreography of the Power Swaps',
        array( 'The Marvels', 'Carol Danvers', 'Monica Rambeau', 'Kamala Khan', 'Phase Five' ),
        'The power swaps bring three characters into a shared problem before they have a shared plan. The staging needs to show where each character starts and what changes when they switch places.',
        'The fights become a measure of teamwork as the group learns to anticipate the swaps. The shift is visible in how quickly each person reacts to a new position.',
    ),
);

$created = 0;
foreach ( get_sites( array( 'network_id' => get_current_network_id(), 'number' => 0, 'deleted' => 0, 'spam' => 0, 'archived' => 0 ) ) as $site ) {
    $id = (int) $site->blog_id;
    if ( ! in_array( NetworkSites::key( $site->path ), array( 'a', 'b' ), true ) || ! Schema::enabled( 'msc_franchise', $id ) ) { continue; }
    switch_to_blog( $id );
    try {
        $created += Database::lock( 'mcu-article-demo-' . $id, static function () use ( $articles ): int {
            if ( get_option( 'msc_mcu_articles_seeded' ) ) { return 0; }
            return Database::transaction( static function () use ( $articles ): int {
                $count = 0;
                $days_ago = 0;
                foreach ( $articles as $slug => list( $title, $tags, $intro, $body ) ) {
                    $date = current_datetime()->modify( '-' . $days_ago++ . ' days' )->format( 'Y-m-d H:i:s' );
                    $post = wp_insert_post( wp_slash( array(
                        'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title,
                        'post_name' => 'demo-' . $slug, 'post_date' => $date,
                        'post_excerpt' => $intro,
                        'post_content' => '<p><em>Sample editorial for the network presentation.</em></p><p>' . esc_html( $intro ) . '</p><p>' . esc_html( $body ) . '</p>',
                        'meta_input' => array( '_msc_mcu_article' => $slug ),
                    ) ), true );
                    if ( is_wp_error( $post ) ) { throw new RuntimeException( $post->get_error_message() ); }
                    $result = wp_set_post_tags( $post, array_merge( array( Franchises::MCU_TAG, 'MCU' ), $tags ) );
                    if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
                    if ( ! $result ) { throw new RuntimeException( 'Could not assign the sample article tags.' ); }
                    ++$count;
                }
                update_option( 'msc_mcu_articles_seeded', 1 );
                return $count;
            } );
        } );
    } finally {
        restore_current_blog();
    }
}
WP_CLI::success( $created . ' MCU sample posts added to Sites A and B.' );
