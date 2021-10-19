<?php
/**
 * Define JJ2Events and JJ2Event classes
 */

namespace J2o\Lib;

use J2o\Exception\JJ2FileException;

/**
 * Class JJ2Events
 *
 * This is one ugly blob but it's just a lot of information to process. This class has information on what an event
 * looks like and how to graphixjjf it. That information is processed and returned based on the event IDs and parameters
 * the J2LFile class passes on to it; the J2LFile class can then use it to graphixjjf events in the level it's dealing with.
 *
 * JJ2, in its infinite wisdom, has a number of events that are rendered as composite of several separate sprites. This
 * class does that compositing.
 *
 * @package J2o\Lib
 */
class JJ2Events {
    /**
     * Event map. Contains info on what animation set and animation to use to graphixjjf an event, and how it is affected
     * by physics. If an event ID is not in this array, it is assumed not to be visible in normal gameplay, and will
     * not
     * be reported as available for rendering.
     *
     * Format: format => Event ID (as in jcs.ini) => (setID, animationID, gravity applies, is pickup, use hotspot for
     * gravity adjustment, adjust even when floating, drawing flag, name)
     *
     * Drawing flag: 0-100 = transparency, 200 = draw as crate, 300 = draw as powerup monitor
     *
     * @var array
     */
    private array $event_map = [
        29 => ['Anims.j2a', 55, 12, false, false, false, false, 100, 'Jazz Level Start'],
        30 => ['Anims.j2a', 89, 12, false, false, false, false, 100, 'Spaz Level Start'],
        31 => ['Anims.j2a', 89, 12, false, false, false, false, 100, 'Multiplayer Level Start'],
        32 => ['Anims.j2a', 61, 12, false, false, false, false, 100, 'Lori Level Start'],
        33 => ['Anims.j2a', 0, 29, false, true, false, false, 100, 'Freezer Ammo+3'],
        34 => ['Anims.j2a', 0, 25, false, true, false, false, 100, 'Bouncer Ammo+3'],
        35 => ['Anims.j2a', 0, 34, false, true, false, false, 100, 'Seeker Ammo+3'],
        36 => ['Anims.j2a', 0, 49, false, true, false, false, 100, '3Way Ammo+3'],
        37 => ['Anims.j2a', 0, 57, false, true, false, false, 100, 'Toaster Ammo+3'],
        38 => ['Anims.j2a', 0, 59, false, true, false, false, 100, 'TNT Ammo+3'],
        39 => ['Anims.j2a', 0, 62, false, true, false, false, 100, 'Gun8 Ammo+3'],
        40 => ['Anims.j2a', 0, 68, false, true, false, false, 100, 'Gun9 Ammo+3'],
        41 => ['Anims.j2a', 103, 4, true, false, false, false, 100, 'Still Turtleshell'],
        42 => ['Anims.j2a', 106, 1, false, false, false, false, 100, 'Swinging Vine'],
        43 => ['Anims.j2a', 0, 1, false, false, false, false, 100, 'Bomb'],
        44 => ['Anims.j2a', 71, 84, false, true, false, false, 100, 'Silver Coin'],
        45 => ['Anims.j2a', 71, 37, false, true, false, false, 100, 'Gold Coin'],
        46 => ['Anims.j2a', 71, 5, true, false, false, false, 100, 'Gun crate'],
        47 => ['Anims.j2a', 71, 5, true, false, false, false, 100, 'Carrot crate'],
        48 => ['Anims.j2a', 71, 5, true, false, false, false, 100, '1Up crate'],
        49 => ['Anims.j2a', 71, 3, true, false, false, false, 100, 'Gem barrel'],
        50 => ['Anims.j2a', 71, 3, true, false, false, false, 100, 'Carrot barrel'],
        51 => ['Anims.j2a', 71, 3, true, false, false, false, 100, '1up barrel'],
        52 => ['Anims.j2a', 71, 5, true, false, false, false, 100, 'Bomb Crate'],
        53 => ['Anims.j2a', 71, 55, true, false, false, false, 100, 'Freezer Ammo+15'],
        54 => ['Anims.j2a', 71, 54, true, false, false, false, 100, 'Bouncer Ammo+15'],
        55 => ['Anims.j2a', 71, 56, true, false, false, false, 100, 'Seeker Ammo+15'],
        56 => ['Anims.j2a', 71, 57, true, false, false, false, 100, '3Way Ammo+15'],
        57 => ['Anims.j2a', 71, 58, true, false, false, false, 100, 'Toaster Ammo+15'],
        58 => ['Anims.j2a', 71, 90, false, false, false, false, 100, 'TNT (armed explosive, no pickup)'],
        59 => ['Anims.j2a', 71, 36, false, true, false, false, 100, 'Airboard'],
        60 => ['Anims.j2a', 96, 5, true, false, false, false, 100, 'Frozen Green Spring'],
        61 => ['Anims.j2a', 71, 29, false, true, false, false, 100, 'Gun Fast Fire'],
        62 => ['Anims.j2a', 71, 5, true, false, false, false, 100, 'Spring Crate'],
        63 => ['Anims.j2a', 71, 22, false, true, false, false, 100, 'Red Gem +1'],
        64 => ['Anims.j2a', 71, 22, false, true, false, false, 100, 'Green Gem +1'],
        65 => ['Anims.j2a', 71, 22, false, true, false, false, 100, 'Blue Gem +1'],
        66 => ['Anims.j2a', 71, 22, false, true, false, false, 100, 'Purple Gem +1'],
        67 => ['Anims.j2a', 71, 34, false, false, false, false, 100, 'Super Red Gem'],
        68 => ['Anims.j2a', 8, 3, true, false, false, false, 100, 'Birdy'],
        69 => ['Anims.j2a', 71, 3, true, false, false, false, 100, 'Gun Barrel'],
        70 => ['Anims.j2a', 71, 5, true, false, false, false, 100, 'Gem Crate'],
        71 => ['Anims.j2a', 71, 70, true, false, false, false, 100, 'Jazz<->Spaz'],
        72 => ['Anims.j2a', 71, 21, false, true, false, false, 100, 'Carrot Energy +1'],
        73 => ['Anims.j2a', 71, 82, false, true, false, false, 100, 'Full Energy'],
        74 => ['Anims.j2a', 71, 31, true, false, false, false, 100, 'Fire Shield'],
        75 => ['Anims.j2a', 71, 10, true, false, false, false, 100, 'Water Shield'],
        76 => ['Anims.j2a', 71, 51, true, false, false, false, 100, 'Lightning Shield'],
        79 => ['Anims.j2a', 71, 33, false, true, false, false, 100, 'Fast Feet'],
        80 => ['Anims.j2a', 71, 0, false, true, false, false, 100, 'Extra Live'],
        81 => ['Anims.j2a', 71, 28, true, false, false, false, 100, 'End of Level signpost'],
        83 => ['Anims.j2a', 71, 14, true, false, false, false, 100, 'Save point signpost'],
        84 => ['Anims.j2a', 11, 0, true, false, false, false, 100, 'Bonus Level signpost'],
        85 => ['Anims.j2a', 96, 7, true, false, false, false, 100, 'Red Spring'],
        86 => ['Anims.j2a', 96, 5, true, false, false, false, 100, 'Green Spring'],
        87 => ['Anims.j2a', 96, 0, true, false, false, false, 100, 'Blue Spring'],
        88 => ['Anims.j2a', 71, 72, false, true, false, false, 100, 'Invincibility'],
        89 => ['Anims.j2a', 71, 87, false, true, false, false, 100, 'Extra Time'],
        90 => ['Anims.j2a', 71, 42, false, true, false, false, 100, 'Freeze Enemies'],
        91 => ['Anims.j2a', 96, 8, false, false, false, false, 100, 'Hor Red Spring'],
        92 => ['Anims.j2a', 96, 6, false, false, false, false, 100, 'Hor Green Spring'],
        93 => ['Anims.j2a', 96, 1, false, false, false, false, 100, 'Hor Blue Spring'],
        95 => ['Anims.j2a', 71, 52, true, false, false, false, 100, 'Scenery Trigger Crate'],
        96 => ['Anims.j2a', 71, 40, false, true, false, false, 100, 'Fly carrot'],
        97 => ['Plus.j2a', 1, 2, false, true, false, false, 100, 'Red RectGem +1'],
        98 => ['Plus.j2a', 1, 2, false, true, false, false, 100, 'Green RectGem +1'],
        99 => ['Plus.j2a', 1, 2, false, true, false, false, 100, 'Blue RectGem +1'],
        100 => ['Anims.j2a', 102, 0, true, false, false, false, 100, 'Tuf Turt'],
        101 => ['Anims.j2a', 101, 5, true, false, false, false, 100, 'Tuf Boss'],
        102 => ['Anims.j2a', 59, 2, true, false, false, false, 100, 'Lab Rat'],
        103 => ['Anims.j2a', 32, 0, true, false, false, false, 100, 'Dragon'],
        104 => ['Anims.j2a', 60, 4, true, false, false, false, 100, 'Lizard'],
        105 => ['Anims.j2a', 15, 0, false, false, false, true, 100, 'Bee'],
        106 => ['Anims.j2a', 76, 2, false, false, false, true, 66, 'Rapier'],
        107 => ['Anims.j2a', 88, 0, false, false, false, true, 100, 'Sparks'],
        108 => ['Anims.j2a', 1, 1, false, false, false, true, 100, 'Bat'],
        109 => ['Anims.j2a', 99, 6, true, false, false, false, 100, 'Sucker'],
        110 => ['Anims.j2a', 20, 0, false, false, false, true, 100, 'Caterpillar'],
        111 => ['Anims.j2a', 18, 2, false, false, false, false, 100, 'Cheshire1'],
        112 => ['Anims.j2a', 19, 2, false, false, false, false, 100, 'Cheshire2'],
        113 => ['Anims.j2a', 52, 4, true, false, false, false, 100, 'Hatter'],
        114 => ['Anims.j2a', 7, 4, true, false, false, false, 100, 'Bilsy Boss'],
        115 => ['Anims.j2a', 83, 2, true, false, false, false, 100, 'Skeleton'],
        116 => ['Anims.j2a', 29, 0, true, false, false, false, 100, 'Doggy Dogg'],
        117 => ['Anims.j2a', 103, 7, true, false, false, false, 100, 'Norm Turtle'],
        118 => ['Anims.j2a', 53, 0, true, false, false, false, 100, 'Helmut'],
        120 => ['Anims.j2a', 24, 0, true, false, false, false, 100, 'Demon'],
        123 => ['Anims.j2a', 31, 0, false, false, false, false, 100, 'Dragon Fly'],
        124 => ['Anims.j2a', 67, 6, true, false, false, false, 100, 'Monkey'],
        125 => ['Anims.j2a', 41, 1, true, false, false, false, 100, 'Fat Chick'],
        126 => ['Anims.j2a', 42, 0, true, false, false, false, 100, 'Fencer'],
        127 => ['Anims.j2a', 43, 0, false, false, false, false, 100, 'Fish'],
        128 => ['Anims.j2a', 68, 3, true, false, false, false, 100, 'Moth'],
        129 => ['Anims.j2a', 97, 0, true, false, false, false, 100, 'Steam'],
        130 => ['Anims.j2a', 79, 0, false, false, false, true, 100, 'Rotating Rock'],
        131 => ['Anims.j2a', 71, 60, true, false, false, false, 100, 'Blaster PowerUp'],
        132 => ['Anims.j2a', 71, 61, true, false, false, false, 100, 'Bouncy PowerUp'],
        133 => ['Anims.j2a', 71, 62, true, false, false, false, 100, 'Ice gun PowerUp'],
        134 => ['Anims.j2a', 71, 63, true, false, false, false, 100, 'Seek PowerUp'],
        135 => ['Anims.j2a', 71, 64, true, false, false, false, 100, 'RF PowerUp'],
        136 => ['Anims.j2a', 71, 65, true, false, false, false, 100, 'Toaster PowerUP'],
        137 => ['Anims.j2a', 72, 4, false, false, false, true, 100, 'PIN => Left Paddle'],
        138 => ['Anims.j2a', 72, 5, false, false, false, true, 100, 'PIN => Right Paddle'],
        139 => ['Anims.j2a', 72, 0, false, false, false, true, 100, 'PIN => 500 Bump'],
        140 => ['Anims.j2a', 72, 2, false, false, false, true, 100, 'PIN => Carrot Bump'],
        141 => ['Anims.j2a', 71, 1, false, true, false, false, 100, 'Apple'],
        142 => ['Anims.j2a', 71, 2, false, true, false, false, 100, 'Banana'],
        143 => ['Anims.j2a', 71, 16, false, true, false, false, 100, 'Cherry'],
        144 => ['Anims.j2a', 71, 71, false, true, false, false, 100, 'Orange'],
        145 => ['Anims.j2a', 71, 74, false, true, false, false, 100, 'Pear'],
        146 => ['Anims.j2a', 71, 79, false, true, false, false, 100, 'Pretzel'],
        147 => ['Anims.j2a', 71, 81, false, true, false, false, 100, 'Strawberry'],
        151 => ['Anims.j2a', 71, 0, true, false, false, false, 100, 'Queen Boss'],
        152 => ['Anims.j2a', 99, 4, false, false, false, false, 100, 'Floating Sucker'],
        153 => ['Anims.j2a', 13, 0, false, false, false, false, 100, 'Bridge'],
        154 => ['Anims.j2a', 71, 48, false, true, false, false, 100, 'Lemon'],
        155 => ['Anims.j2a', 71, 50, false, true, false, false, 100, 'Lime'],
        156 => ['Anims.j2a', 71, 89, false, true, false, false, 100, 'Thing'],
        157 => ['Anims.j2a', 71, 92, false, true, false, false, 100, 'Watermelon'],
        158 => ['Anims.j2a', 71, 73, false, true, false, false, 100, 'Peach'],
        159 => ['Anims.j2a', 71, 38, false, true, false, false, 100, 'Grapes'],
        160 => ['Anims.j2a', 71, 49, false, true, false, false, 100, 'Lettuce'],
        161 => ['Anims.j2a', 71, 26, false, true, false, false, 100, 'Eggplant'],
        162 => ['Anims.j2a', 71, 23, false, true, false, false, 100, 'Cucumb'],
        163 => ['Anims.j2a', 71, 20, false, true, false, false, 100, 'Soft Drink'],
        164 => ['Anims.j2a', 71, 75, false, true, false, false, 100, 'Soda Pop'],
        165 => ['Anims.j2a', 71, 53, false, true, false, false, 100, 'Milk'],
        166 => ['Anims.j2a', 71, 76, false, true, false, false, 100, 'Pie'],
        167 => ['Anims.j2a', 71, 12, false, true, false, false, 100, 'Cake'],
        168 => ['Anims.j2a', 71, 25, false, true, false, false, 100, 'Donut'],
        169 => ['Anims.j2a', 71, 24, false, true, false, false, 100, 'Cupcake'],
        170 => ['Anims.j2a', 71, 18, false, true, false, false, 100, 'Chips'],
        171 => ['Anims.j2a', 71, 13, false, true, false, false, 100, 'Candy'],
        172 => ['Anims.j2a', 71, 19, false, true, false, false, 100, 'Chocbar'],
        173 => ['Anims.j2a', 71, 43, false, true, false, false, 100, 'Icecream'],
        174 => ['Anims.j2a', 71, 11, false, true, false, false, 100, 'Burger'],
        175 => ['Anims.j2a', 71, 77, false, true, false, false, 100, 'Pizza'],
        176 => ['Anims.j2a', 71, 32, false, true, false, false, 100, 'Fries'],
        177 => ['Anims.j2a', 71, 17, false, true, false, false, 100, 'Chicken Leg'],
        178 => ['Anims.j2a', 71, 80, false, true, false, false, 100, 'Sandwich'],
        179 => ['Anims.j2a', 71, 88, false, true, false, false, 100, 'Taco'],
        180 => ['Anims.j2a', 71, 91, false, true, false, false, 100, 'Weenie'],
        181 => ['Anims.j2a', 71, 39, false, true, false, false, 100, 'Ham'],
        182 => ['Anims.j2a', 71, 15, false, true, false, false, 100, 'Cheese'],
        183 => ['Anims.j2a', 60, 2, false, false, false, true, 100, 'Float Lizard'],
        184 => ['Anims.j2a', 67, 2, true, false, false, false, 100, 'Stand Monkey'],
        190 => ['Anims.j2a', 77, 1, false, false, false, true, 100, 'Raven'],
        191 => ['Anims.j2a', 100, 0, true, false, false, false, 100, 'Tube Turtle'],
        192 => ['Anims.j2a', 71, 35, false, false, false, true, 100, 'Gem Ring'],
        193 => ['Anims.j2a', 84, 0, true, true, true, false, 100, 'Small Tree'],
        195 => ['Anims.j2a', 105, 0, false, false, false, true, 100, 'Uterus'],
        196 => ['Anims.j2a', 105, 7, true, false, false, false, 100, 'Crab'],
        197 => ['Anims.j2a', 112, 0, false, false, false, false, 100, 'Witch'],
        198 => ['Anims.j2a', 80, 1, false, false, false, true, 100, 'Rocket Turtle'],
        199 => ['Anims.j2a', 14, 0, true, false, false, false, 100, 'Bubba'],
        200 => ['Anims.j2a', 27, 8, true, false, false, false, 100, 'Devil devan boss'],
        201 => ['Anims.j2a', 26, 1, false, false, false, false, 100, 'Devan (robot boss)'],
        202 => ['Anims.j2a', 78, 3, false, false, false, false, 100, 'Robot (robot boss)'],
        203 => ['Anims.j2a', 17, 0, true, true, true, false, 100, 'Carrotus pole'],
        204 => ['Anims.j2a', 74, 0, true, true, true, false, 100, 'Psych pole'],
        205 => ['Anims.j2a', 28, 0, true, true, true, false, 100, 'Diamondus pole'],
        209 => ['Anims.j2a', 48, 0, false, false, false, false, 100, 'Fruit Platform'],
        210 => ['Anims.j2a', 10, 0, false, false, false, false, 100, 'Boll Platform'],
        211 => ['Anims.j2a', 51, 0, false, false, false, false, 100, 'Grass Platform'],
        212 => ['Anims.j2a', 73, 0, false, false, false, false, 100, 'Pink Platform'],
        213 => ['Anims.j2a', 87, 0, false, false, false, false, 100, 'Sonic Platform'],
        214 => ['Anims.j2a', 95, 0, false, false, false, false, 100, 'Spike Platform'],
        215 => ['Anims.j2a', 93, 0, false, false, false, false, 100, 'Spike Boll'],
        217 => ['Anims.j2a', 38, 0, true, false, false, false, 100, 'Eva'],
        220 => ['Anims.j2a', 71, 66, true, false, false, false, 100, 'Gun8 Powerup'],
        221 => ['Anims.j2a', 71, 67, true, false, false, false, 100, 'Gun9 Powerup'],
        223 => ['Anims.j2a', 93, 0, false, false, false, false, 100, '3D Spike Boll'],
        226 => ['Anims.j2a', 60, 3, false, false, false, true, 100, 'Copter'],
        227 => ['Plus.j2a', 2, 2, true, false, false, false, 100, 'Laser Shield'],
        228 => ['Anims.j2a', 71, 87, false, true, false, false, 100, 'Stopwatch'],
        229 => ['Anims.j2a', 58, 0, true, true, true, false, 100, 'Jungle Pole'],
        231 => ['Anims.j2a', 5, 0, true, false, false, false, 100, 'Big Rock'],
        232 => ['Anims.j2a', 4, 0, true, false, false, false, 100, 'Big Box'],
        235 => ['Anims.j2a', 86, 2, false, false, false, false, 100, 'Bolly Boss'],
        236 => ['Anims.j2a', 16, 0, false, false, false, true, 100, 'Butterfly'],
        237 => ['Anims.j2a', 3, 0, false, false, false, true, 100, 'BeeBoy'],
        244 => ['Anims.j2a', 44, 1, true, false, false, false, 100, 'CTF Base + Flag'],
        247 => ['Anims.j2a', 113, 4, true, false, false, false, 100, 'Xmas Bilsy Boss'],
        248 => ['Anims.j2a', 115, 7, true, false, false, false, 100, 'Xmas Norm Turtle'],
        249 => ['Anims.j2a', 114, 4, true, false, false, false, 100, 'Xmas Lizard'],
        250 => ['Anims.j2a', 114, 2, false, false, false, true, 100, 'Xmas Float Lizard'],
        251 => ['Anims.j2a', 113, 0, true, false, false, false, 100, 'Addon DOG'], //actually xmas bilsy
        252 => ['Anims.j2a', 116, 1, true, false, false, false, 100, 'Addon Sparks'], //actually a cat
        253 => ['Anims.j2a', 117, 0, false, false, false, true, 100, 'Blue Ghost'], //actually a ghost
        //the next few are 'meta' events without their own event but may replace
        //other events with a sprite from Plus.j2a, event ID >= 300
        300 => ['Anims.j2a', 71, 58, true, false, false, false, 100, 'TNT Ammo+15'],
        301 => ['Plus.j2a', 2, 0, true, false, false, false, 100, 'Gun8 Ammo+15'],
        302 => ['Plus.j2a', 2, 1, true, false, false, false, 100, 'Gun9 Ammo+15'],
        //custom weapons, event ID >= 500
        //3 per weapon; +3 pickup, +15 crate, powerup monitor
        500 => ['SEroller.j2a', 0, 0, false, true, false, false, 100, 'Roller Ammo+3'],
        501 => ['SEroller.j2a', 0, 2, true, false, false, false, 100, 'Roller Ammo+15'],
        502 => ['SEroller.j2a', 0, 3, true, false, false, false, 100, 'Roller Powerup'],
        510 => ['SEfirework.j2a', 0, 0, false, true, false, false, 100, 'Firework Ammo+3'],
        511 => ['SEfirework.j2a', 0, 2, true, false, false, false, 100, 'Firework Ammo+15'],
        512 => ['SEfirework.j2a', 0, 3, true, false, false, false, 100, 'Firework Powerup'],
        520 => ['SEenergyblast.j2a', 0, 0, false, true, false, false, 100, 'Energy Blast Ammo+3'],
        521 => ['SEenergyblast.j2a', 0, 2, true, false, false, false, 100, 'Energy Blast Ammo+15'],
        522 => ['SEenergyblast.j2a', 0, 3, true, false, false, false, 100, 'Energy Blast Powerup'],
        530 => ['BubbleGun-mlle.j2a', 0, 0, false, true, false, false, 100, 'Bubble Gun Ammo+3'],
        531 => ['BubbleGun-mlle.j2a', 0, 1, true, false, false, false, 100, 'Bubble Gun Ammo+15'],
        532 => ['BubbleGun-mlle.j2a', 0, 2, true, false, false, false, 100, 'Bubble Gun Powerup'],
        540 => ['CosmicDust.j2a', 0, 1, false, true, false, false, 100, 'Cosmic Dust Ammo+3'],
        541 => ['CosmicDust.j2a', 0, 3, true, false, false, false, 100, 'Cosmic Dust Ammo+15'],
        542 => ['CosmicDust.j2a', 0, 4, true, false, false, false, 100, 'Cosmic Dust Powerup'],
        550 => ['dischargeGun.j2a', 0, 3, false, true, false, false, 100, 'Discharge Gun Ammo+3'],
        551 => ['dischargeGun.j2a', 0, 3, true, false, false, false, 200, 'Discharge Gun Ammo+15'],
        552 => ['dischargeGun.j2a', 0, 4, true, false, false, false, 100, 'Discharge Gun Powerup'],
        560 => ['flashbang.j2a', 0, 2, false, true, false, false, 100, 'Flashbang Ammo+3'],
        561 => ['flashbang.j2a', 0, 2, true, false, false, false, 200, 'Flashbang Ammo+15'],
        562 => ['flashbang.j2a', 0, 3, true, false, false, false, 100, 'Flashbang Powerup'],
        570 => ['FusionCannon.j2a', 0, 0, false, true, false, false, 100, 'Fusion Cannon Ammo+3'],
        571 => ['FusionCannon.j2a', 0, 3, true, false, false, false, 100, 'Fusion Cannon Ammo+15'],
        572 => ['FusionCannon.j2a', 0, 2, true, false, false, false, 100, 'Fusion Cannon Powerup'],
        580 => ['LaserBlaster.j2a', 0, 2, false, true, false, false, 100, 'Laser Blaster Ammo+3'],
        581 => ['LaserBlaster.j2a', 0, 2, true, false, false, false, 200, 'Laser Blaster Ammo+15'],
        582 => ['LaserBlaster.j2a', 0, 4, true, false, false, false, 100, 'Laser Blaster Powerup'],
        590 => ['Lightningrod.j2a', 0, 0, false, true, false, false, 100, 'Lightningrod Ammo+3'],
        591 => ['Lightningrod.j2a', 0, 5, true, false, false, false, 100, 'Lightningrod Ammo+15'],
        592 => ['Lightningrod.j2a', 0, 0, true, false, false, false, 300, 'Lightningrod Powerup'],
        600 => ['lockOnMissile.j2a', 0, 3, false, true, false, false, 100, 'Lock-On Missile Ammo+3'],
        601 => ['lockOnMissile.j2a', 0, 3, true, false, false, false, 200, 'Lock-On Missile Ammo+15'],
        602 => ['lockOnMissile.j2a', 0, 5, true, false, false, false, 100, 'Lock-On Missile Powerup'],
        610 => ['Meteor.j2a', 0, 1, false, true, false, false, 100, 'Meteor Ammo+3'],
        611 => ['Meteor.j2a', 0, 3, true, false, false, false, 100, 'Meteor Ammo+15'],
        612 => ['Meteor.j2a', 0, 4, true, false, false, false, 100, 'Meteor Powerup'],
        620 => ['Mortar.j2a', 0, 1, false, true, false, false, 100, 'Mortar Ammo+3'],
        621 => ['Mortar.j2a', 0, 4, true, false, false, false, 100, 'Mortar Ammo+15'],
        622 => ['Mortar.j2a', 0, 5, true, false, false, false, 100, 'Mortar Powerup'],
        630 => ['Nail.j2a', 0, 4, false, true, false, false, 100, 'Nailgun Ammo+3'],
        631 => ['Nail.j2a', 0, 2, true, false, false, false, 100, 'Nailgun Ammo+15'],
        632 => ['Nail.j2a', 0, 3, true, false, false, false, 100, 'Nailgun Powerup'],
        640 => ['petrolBomb.j2a', 0, 3, false, true, false, false, 100, 'Petrol Bomb Ammo+3'],
        641 => ['petrolBomb.j2a', 0, 3, true, false, false, false, 200, 'Petrol Bomb Ammo+15'],
        642 => ['petrolBomb.j2a', 0, 3, true, false, false, false, 300, 'Petrol Bomb Powerup'],
        650 => ['sword.j2a', 0, 3, false, true, false, false, 100, 'Sword Ammo+3'],
        651 => ['sword.j2a', 0, 3, true, false, false, false, 200, 'Sword Ammo+15'],
        652 => ['sword.j2a', 0, 3, true, false, false, false, 300, 'Sword Powerup'],
        660 => ['Syringe.j2a', 0, 0, false, true, false, false, 100, 'Syringe Ammo+3'],
        661 => ['Syringe.j2a', 0, 3, true, false, false, false, 100, 'Syringe Ammo+15'],
        662 => ['Syringe.j2a', 0, 2, true, false, false, false, 100, 'Syringe Powerup'],
        670 => ['TornadoGun.j2a', 0, 3, false, true, false, false, 100, 'Tornado Gun Ammo+3'],
        671 => ['TornadoGun.j2a', 0, 5, true, false, false, false, 100, 'Tornado Gun Ammo+15'],
        672 => ['TornadoGun.j2a', 0, 4, true, false, false, false, 100, 'Tornado Gun Powerup'],
        680 => ['weaponVMega.j2a', 0, 1, false, true, false, false, 100, 'Boomerang Ammo+3'],
        681 => ['weaponVMega.j2a', 0, 1, true, false, false, false, 200, 'Boomerang Ammo+15'],
        682 => ['weaponVMega.j2a', 0, 1, true, false, false, false, 300, 'Boomerang Powerup'],
        690 => ['weaponVMega.j2a', 1, 6, false, true, false, false, 100, 'Burrower Ammo+3'],
        691 => ['weaponVMega.j2a', 1, 6, true, false, false, false, 200, 'Burrower Ammo+15'],
        692 => ['weaponVMega.j2a', 1, 6, true, false, false, false, 300, 'Burrower Powerup'],
        700 => ['weaponVMega.j2a', 2, 3, false, true, false, false, 100, 'Ice Cloud Ammo+3'],
        701 => ['weaponVMega.j2a', 2, 3, true, false, false, false, 200, 'Ice Cloud Ammo+15'],
        702 => ['weaponVMega.j2a', 2, 3, true, false, false, false, 300, 'Ice Cloud Powerup'],
        710 => ['weaponVMega.j2a', 3, 4, false, true, false, false, 100, 'Pathfinder Ammo+3'],
        711 => ['weaponVMega.j2a', 3, 4, true, false, false, false, 200, 'Pathfinder Ammo+15'],
        712 => ['weaponVMega.j2a', 3, 4, true, false, false, false, 300, 'Pathfinder Powerup'],
        720 => ['weaponVMega.j2a', 4, 2, false, true, false, false, 100, 'Backfire Ammo+3'],
        721 => ['weaponVMega.j2a', 4, 2, true, false, false, false, 200, 'Backfire Ammo+15'],
        722 => ['weaponVMega.j2a', 4, 2, true, false, false, false, 300, 'Backfire Powerup'],
        730 => ['weaponVMega.j2a', 5, 2, false, true, false, false, 100, 'Crackerjack Ammo+3'],
        731 => ['weaponVMega.j2a', 5, 2, true, false, false, false, 200, 'Crackerjack Ammo+15'],
        732 => ['weaponVMega.j2a', 5, 2, true, false, false, false, 300, 'Crackerjack Powerup'],
        740 => ['weaponVMega.j2a', 6, 1, false, true, false, false, 100, 'Gravity Well Ammo+3'],
        741 => ['weaponVMega.j2a', 6, 1, true, false, false, false, 200, 'Gravity Well Ammo+15'],
        742 => ['weaponVMega.j2a', 6, 1, true, false, false, false, 300, 'Gravity Well Powerup'],
        750 => ['weaponVMega.j2a', 7, 2, false, true, false, false, 100, 'Voranj Ammo+3'],
        751 => ['weaponVMega.j2a', 7, 2, true, false, false, false, 200, 'Voranj Ammo+15'],
        752 => ['weaponVMega.j2a', 7, 2, true, false, false, false, 300, 'Voranj Powerup'],
        760 => ['SmokeWopens.j2a', 0, 0, false, true, false, false, 100, 'ELEKTREK SHIELD Ammo+3'],
        761 => ['SmokeWopens.j2a', 0, 0, true, false, false, false, 200, 'ELEKTREK SHIELD Ammo+15'],
        762 => ['SmokeWopens.j2a', 0, 0, true, false, false, false, 300, 'ELEKTREK SHIELD Powerup'],
        770 => ['SmokeWopens.j2a', 1, 1, false, true, false, false, 100, 'Zeus Artillery Ammo+3'],
        771 => ['SmokeWopens.j2a', 1, 1, true, false, false, false, 200, 'Zeus Artillery Ammo+15'],
        772 => ['SmokeWopens.j2a', 1, 1, true, false, false, false, 300, 'Zeus Artillery Powerup'],
        780 => ['SmokeWopens.j2a', 2, 0, false, true, false, false, 100, 'Phoenix Gun Ammo+3'],
        781 => ['SmokeWopens.j2a', 2, 0, true, false, false, false, 200, 'Phoenix Gun Ammo+15'],
        782 => ['SmokeWopens.j2a', 2, 0, true, false, false, false, 300, 'Phoenix Gun Powerup'],
        790 => ['autoTurret.j2a', 0, 1, false, true, false, false, 100, 'Auto-turret Ammo+3'],
        791 => ['autoTurret.j2a', 0, 1, true, false, false, false, 200, 'Auto-turret Ammo+15'],
        792 => ['autoTurret.j2a', 0, 1, true, false, false, false, 300, 'Auto-turret Powerup'],
        800 => ['weaponVMega.j2a', 8, 4, false, true, false, false, 100, 'Meteor V Ammo+3'],
        801 => ['weaponVMega.j2a', 8, 4, true, false, false, false, 200, 'Meteor V Ammo+15'],
        802 => ['weaponVMega.j2a', 8, 4, true, false, false, false, 300, 'Meteor V Powerup'],
        810 => ['SEminimirv.j2a', 0, 0, false, true, false, false, 100, 'Mini-MIRV Ammo+3'],
        811 => ['SEminimirv.j2a', 0, 2, true, false, false, false, 100, 'Mini-MIRV Ammo+15'],
        812 => ['SEminimirv.j2a', 0, 3, true, false, false, false, 100, 'Mini-MIRV Powerup']
    ];

    /**
     * @var array Parsed events are stored in this array with their event bytes (ID + params) as key, for later re-use
     */
    private array $event_cache = [];
    /**
     * @var array Loaded J2A Libraries, as filename => J2AFile array
     */
    private array $j2a = [];
    /**
     * @var array  Palette to use for rendering sprites
     */
    private array $palette;
    /**
     * @var array  Event redirection - replace all events with this ID with the other ID
     */
    private array $redirect = [];
    /**
     * @var string  Resource folder, containing animation libraries
     */
    private string $resource_folder = '';
    /**
     * @var array  Palette remappings for specific sprites, with sprite setIDs as keys and
     * each setID being an array with animIDs as key and a 256-colour palette as value
     */
    private array $palette_remapping = [];


    /**
     * JJ2Events constructor.
     *
     * @param array $palette
     */
    public function __construct(array $palette, string $resource_folder, $palette_remapping = []) {
        $this->j2a = [];
        $this->palette = $palette;
        $this->resource_folder = $resource_folder;
        $this->palette_remapping = $palette_remapping;
    }

    /**
     * Get animation library handle
     *
     * Instantiates a J2AFile object for a given library file, if it has not
     * been instantiated yet, and returns that object.
     *
     * @param string $filename Library file name
     * @return J2AFile  Library object
     * @throws JJ2FileException  If the library file does not exist
     */
    public function get_library(string $filename): J2AFile {
        $path = $this->resource_folder.DIRECTORY_SEPARATOR.$filename;
        if (!file_exists($path)) {
            throw new JJ2FileException('Animation library '.$path.' not found.');
        }

        if (!array_key_exists($filename, $this->j2a)) {
            $this->j2a[$filename] = new J2AFile($path, $this->palette);
            $this->j2a[$filename]->load_remapping($this->palette_remapping);
        }

        return $this->j2a[$filename];
    }

    /**
     * Use another event ID's mapping for a given event ID
     *
     * @param int $from Event ID to replace
     * @param int $to Event ID to replace with
     */
    public function redirect(int $from, int $to): void {
        $this->redirect[$from] = $to;
    }

    /**
     * Resolve redirect
     *
     * Event ID redirects may redirect to an ID that itself should be
     * redirected. This method resolves such chained redirects. It will
     * enter an infinite loop if there is an infinite redirect loop,
     * but that would be silly!
     *
     * @param int $from Event ID to resolve for
     * @return int  Event ID to redirect to
     * @throws JJ2FileException  If the event ID this resolves to is unknown
     */
    public function resolve_redirect(int $from): int {
        while (array_key_exists($from, $this->redirect)) {
            $from = $this->redirect[$from];
        }

        if (!array_key_exists($from, $this->event_map)) {
            throw new JJ2FileException('Event ID '.$from.' unknown');
        }

        return $from;
    }


    /**
     * Get event parameters
     *
     * @param string $event_data Event data, 32 bits
     * @param int $bit_offset Offset after which to start looking. Will be incremented by 8 to account
     *                            for the first 8 bits being the event ID
     * @param int $length Amount of bits to read
     *
     * @return  int  Value for given parameter
     */
    public static function get_event_param(string $event_data, int $bit_offset, int $length): int {
        $event_data = $event_data >> $bit_offset;
        $event_data = $event_data & pow(2, $length) - 1;

        return $event_data;
    }


    /**
     * Create empty image of given proportions
     *
     * Also sets image transparency colour to the first colour of the palette, making this a useful method to obtain
     * image resources to render sprites, etc, to.
     *
     * @param int $x Image width
     * @param int $y Image height
     *
     * @return resource  GD Image resource
     */
    public function get_empty_image(int $x, int $y) {
        $img = imagecreatetruecolor($x, $y);
        $transparent = imagecolorallocatealpha($img, $this->palette[0][0], $this->palette[0][1], $this->palette[0][2], 127);
        imagefill($img, 0, 0, $transparent);
        imagealphablending($img, true);

        unset($transparent);

        return $img;
    }


    /**
     * Check if event is available for rendering
     *
     * @param int $event_ID
     *
     * @return bool  If event is available for rendering
     */
    public function is_visible(int $event_ID): bool {
        return isset($this->event_map[$event_ID]);
    }


    /**
     * Retrieves event sprite and rendering info
     *
     * Gets event sprites from J2A, and in some cases combines and edits sprites to get a composite that matches the
     * way JJ2 itself draws the event. Also returns rendering info indicating positioning and physics handling. Results
     * are cached; events with the same ID and parameters are not generated again but re-used.
     *
     * @param int $event_bytes Event bytes, i.e. int containing both event ID and params
     * @param bool $on_ground Is the event resting on something solid?
     *
     * @return JJ2Event   Object containing both sprite and rendering info
     * @throws JJ2FileException  If event ID is not available
     */
    public function get_event(int $event_bytes, bool $on_ground = false): JJ2Event {
        $event_ID = $event_bytes & 255;
        $event_ID = $this->resolve_redirect($event_ID);

        $event_params = $event_bytes >> 12; //skip the event ID bits and some other parameters

        //we can cache most events, but some will change depending on factors other than their own parameters (notably
        //springs are flipped based on surrounding tiles and parameters), so only use cache for 'static' events
        if (isset($this->event_cache[$event_bytes]) && !in_array($event_ID, [29, 30, 31, 32, 85, 86, 87, 91, 92, 93])) {
            return $this->event_cache[$event_bytes];
        }

        $event = new JJ2Event;
        if (!isset($this->event_map[$event_ID])) {
            throw new JJ2FileException('event '.$event_ID.' not known');
        }
        $set_ID = $this->event_map[$event_ID][1];
        $anim_ID = $this->event_map[$event_ID][2];
        $frame_ID = 0;
        $lut = NULL;

        $j2a = $this->get_library($this->event_map[$event_ID][0]);

        $event->feels_gravity = $this->event_map[$event_ID][3];
        $event->is_pickup = $this->event_map[$event_ID][4];
        $event->use_hotspot = $this->event_map[$event_ID][5];
        $event->always_adjust = $this->event_map[$event_ID][6];
        $event->opacity = $this->event_map[$event_ID][7];
        $event->difficulty = self::get_event_param($event_bytes, 8, 2);

        $event->draw_mode = JJ2Event::DRAW_NORMAL;
        if ($event->opacity > 100) {
            if ($event->opacity == 200) {
                $event->draw_mode = JJ2Event::DRAW_CRATE;
            } elseif ($event->opacity == 300) {
                $event->draw_mode = JJ2Event::DRAW_MONITOR;
            }

            $event->opacity = 100;
        }

        switch ($event_ID) {
            case 209: //fruit platform
            case 210: //boll platform
            case 211: //grass platform
            case 212: //pink platform
            case 213: //sonic platform
            case 214: //spike platform
            case 215: //spike boll
            case 223: //3d spike boll
                $length = self::get_event_param($event_params, 8, 4);

                if ($event_ID == 223 || $event_ID == 215) {
                    //spike bolls
                    $spike_boll = true;
                } else {
                    //platforms
                    $spike_boll = false;
                }

                $platform = $j2a->get_frame($set_ID, 0, 0);
                $chain = $j2a->get_frame($set_ID, 0, 1);

                $chain_length = $length > 2 ? $length - 2 : 0;
                $chain_overlap = ($spike_boll || $event_ID == 210) ? 2 : 0; //metal chains overlap, other "chain" sprites don't

                $width = $platform[0]['width'];
                if($length > 0) {
                    if(!$spike_boll) {
                        $event->offset_y += 11;
                    }
                    $height = (($chain[0]['height'] - $chain_overlap) * 2) + (($chain[0]['height'] - $chain_overlap) * $chain_length) + $platform[0]['height'];
                } else {
                    $height = $platform[0]['height'];
                }
                $swinging_platform = $this->get_empty_image($width, $height);

                if ($spike_boll) {
                    imagecopy($swinging_platform, $platform[1], 0, imagesy($swinging_platform) - $platform[0]['height'], 0, 0, $platform[0]['width'], $platform[0]['height']);
                }

                $y = imagesy($swinging_platform) - $platform[0]['height'] - $platform[0]['hotspoty'] + $chain[0]['hotspoty'];
                $x = abs($platform[0]['hotspotx']) - abs($chain[0]['hotspotx']);

                for ($link = 0; $link <= $length; $link += 1) {
                    imagecopy($swinging_platform, $chain[1], $x, $y, 0, 0, $chain[0]['width'], $chain[0]['height']);
                    $y -= ($chain[0]['height'] - $chain_overlap);
                }

                if (!$spike_boll) {
                    imagecopy($swinging_platform, $platform[1], 0, imagesy($swinging_platform) - $platform[0]['height'], 0, 0, $platform[0]['width'], $platform[0]['height']);
                }

                $frame[1] = $swinging_platform;
                $frame[0] = [
                    'width' => imagesx($swinging_platform),
                    'height' => imagesy($swinging_platform),
                    'hotspotx' => 0,
                    'hotspoty' => 0,
                    'coldspotx' => 0,
                    'coldspoty' => 0
                ];
                $event->offset_x -= 11;

                break;

            case 244: //ctf base
                $team = self::get_event_param($event_params, 0, 1);
                $flipped = (self::get_event_param($event_params, 1, 1) == 0);
                if ($team > 0) { //red
                    $frame_ID = 1;
                }

                $base = $this->get_empty_image(130, 101);
                $machine = $j2a->get_frame($set_ID, 1, $frame_ID);
                $eva = $j2a->get_frame($set_ID, 5, 0);
                $flag = $j2a->get_frame($set_ID, (($team > 0) ? 7 : 3), 0);
                $beepboop = $j2a->get_frame($set_ID, 2, 0);

                imageflip($eva[1], IMG_FLIP_HORIZONTAL); //eva is always in the opposite direction wrt base

                if ($flipped) {
                    imageflip($flag[1], IMG_FLIP_HORIZONTAL); //flag is always in the original direction
                }

                imagecopy($base, $machine[1], 45, 0, 0, 0, imagesx($machine[1]), imagesy($machine[1]));
                imagecopy($base, $eva[1], 0, 40, 0, 0, imagesx($eva[1]), imagesy($eva[1]));
                imagecopy($base, $beepboop[1], 102, 42, 0, 0, imagesx($beepboop[1]), imagesy($beepboop[1]));
                imagecopy($base, $flag[1], ($flipped ? 33 : 78), 54, 0, 0, imagesx($flag[1]), imagesy($flag[1]));

                $frame[1] = $base;
                $frame[0] = [
                    'width' => imagesx($base),
                    'height' => imagesy($base),
                    'hotspotx' => 0 - ($machine[0]['width'] + $machine[0]['hotspotx']) - 12,
                    'hotspoty' => $machine[0]['hotspoty'],
                    'coldspotx' => 0 - ($machine[0]['width'] + $machine[0]['coldspotx']) - 12,
                    'coldspoty' => $machine[0]['coldspoty']
                ];

                $event->offset_x += $frame[0]['hotspotx'];
                $event->flip_x = $flipped;
                break;

            case 85: //red spring
            case 86: //green spring
            case 87: //blue spring
                $ceiling = self::get_event_param($event_params, 0, 1);
                if ($ceiling != 0) {
                    $event->feels_gravity = false;
                    $event->flip_y = true;
                }
                break;

            case 91: //hor red spring
            case 92: //hor green spring
            case 93: //hor blue spring
                $event->offset_y = -1;
                break;

            case 153: //bridge
                $bridge_tiles = self::get_event_param($event_params, 0, 4);
                if ($bridge_tiles == 0) {
                    break;
                }
                $type = min(6, self::get_event_param($event_params, 4, 3));
                $length = $bridge_tiles * 32;
                $width = 0;

                list(, , $anim) = $j2a->get_frame($set_ID, $anim_ID + $type, 0);
                $current_frame = 0;
                $bridge = $this->get_empty_image($length, 32);

                $max_height = 0;
                $finished = false;

                while ($width < $length) {
                    $segment = $j2a->get_frame($set_ID, $anim_ID + $type, $current_frame);
                    if ($segment[0]['height'] > $max_height) {
                        $max_height = $segment[0]['height'];
                    }

                    if (($width + $segment[0]['width']) > $length && !$finished) {
                        $bridge_enlarged = $this->get_empty_image($width + $segment[0]['width'], imagesy($bridge));
                        imagecopy($bridge_enlarged, $bridge, 0, 0, 0, 0, imagesx($bridge), imagesy($bridge));
                        $bridge = $bridge_enlarged;
                        unset($bridge_enlarged);
                        $length = $width + $segment[0]['width'];
                        $finished = true;
                    }

                    imagecopy($bridge, $segment[1], $width, 10 + $segment[0]['hotspoty'], 0, 0, $segment[0]['width'], $segment[0]['height']);

                    $width += $segment[0]['width'];
                    if ($current_frame == ($anim['framecount'] - 1)) {
                        $current_frame = 0;
                    } else {
                        $current_frame += 1;
                    }
                }

                $frame[1] = $bridge;
                $frame[0]['width'] = $length;
                $frame[0]['height'] = 32;
                $event->offset_x -= 1;
                break;

            case 29: //jazz level start
            case 30: //spaz
            case 31: //MP
            case 32: //lori
                $event->flip_x = rand(0, 4) < 2;

                //mp start: either jazz or spaz or lori
                if ($event_ID == 31) {
                    $set_ID = array_rand_value([55, 89, 61]);
                }

                if ($on_ground) {
                    $anim_ID = array_rand_value([6, 10, 14, 15, 30, 34]);
                    $event->feels_gravity = true;
                } else {
                    $anim_ID = array_rand_value([11, 12, 25, 27, 51, 60]);
                }

                $frame_ID = 0;

                break;

            case 63: //red gem
            case 64: //green gem
            case 65: //blue gem
            case 66: //pruple gem
            case 67: //giant red gem
            case 97: //rectgem red
            case 98: //rectgem green
            case 99: //rectgem blue
                $shift = 0;
                $lut = [ //*frowns at arjan*
                    23, 23, 22, 21, 20, 19, 18, 17, 16, 15, 16, 15, 15, 16, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15,
                    15, 15, 15, 15, 0, 0, 0
                ]; //green gem
                if ($event_ID == 63 || $event_ID == 67 || $event_ID == 97) { //red gem and super red gem and rectgem red
                    $shift = 32;
                }
                if ($event_ID == 65 || $event_ID == 99) { //blue gem and rectgem green
                    $shift = 16;
                }
                if ($event_ID == 66) { //purple gem
                    $shift = 72;
                }
                foreach ($lut as $j => $index) {
                    if ($index > 15) {
                        $lut[$j] = $index + $shift;
                    }
                }
                break;

            case 192: // gem ring
                $length = self::get_event_param($event_params, 0, 5);
                $event_num = self::get_event_param($event_params,10, 8);

                if($event_num == 0) {
                    // default to red gem
                    $event_num = $this->resolve_redirect(63);
                } else {
                    $event_num = $this->resolve_redirect($event_num);
                }

                if($event_num == 192) {
                    // ha ha no
                    break;
                }

                if($length == 0) {
                    $length = 8;
                }

                // the ring is about 96x96, but get a larger image to account for the sprites in the ring being larger
                // than that, possibly. basically, we're drawing a ring of sprites with a 96px radius and all sprites
                // centred on some point on that ring
                $ring_event = $this->get_event($event_num);
                $gem_ring = $this->get_empty_image(256, 256);

                // we need some space to avoid clipping after rotating, so give the sprite 50% margin
                $gem_sprite = $ring_event->sprite[1];
                $rotatable = $this->get_empty_image(imagesx($gem_sprite) * 2, imagesy($gem_sprite) * 2);
                $center_x = (imagesx($rotatable) / 2) - (imagesx($gem_sprite) / 2);
                $center_y = (imagesx($rotatable) / 2) - (imagesx($gem_sprite) / 2);
                imagecopy($rotatable, $gem_sprite, $center_x, $center_y, 0, 0, imagesx($gem_sprite), imagesy($gem_sprite));

                // parameters for rotating... this seems reasonably close to what jj2 does
                $angle = 25;
                $angle_step = deg2rad(360 / $length);
                $radius = 45;
                $transparent = imagecolorallocatealpha($rotatable, $this->palette[0][0], $this->palette[0][1], $this->palette[0][2], 127);

                // render sprites in a ring
                for($i = 0; $i < $length; $i += 1) {
                    $x_offset = cos($angle) * $radius;
                    $y_offset = tan($angle) * $x_offset;

                    $x = (imagesx($gem_ring) / 2) + $x_offset;
                    $y = (imagesy($gem_ring) / 2) - $y_offset;

                    $angled_sprite = imagerotate($rotatable, (rad2deg($angle) - 90) % 360, $transparent);
                    //$angled_sprite = $ring_event->sprite[1];
                    $x -= imagesx($angled_sprite) / 2;
                    $y -= imagesy($angled_sprite) / 2;

                    imagecopy($gem_ring, $angled_sprite, $x, $y, 0, 0, imagesx($angled_sprite), imagesy($angled_sprite));

                    $angle += $angle_step;
                }

                $frame[1] = $gem_ring;
                $frame[0] = [
                    'width' => imagesx($gem_ring),
                    'height' => imagesy($gem_ring),
                    'hotspotx' => -(imagesx($gem_ring) / 2),
                    'hotspoty' => -(imagesy($gem_ring) / 2),
                    'coldspotx' => 0,
                    'coldspoty' => 0
                ];
                break;

            case 128: //moths
                $type = self::get_event_param($event_params, 0, 3);
                switch ($type) {
                    default:
                        $anim_ID = 3;
                        break;
                    case 1:
                    case 5:
                        $anim_ID = 1;
                        break;
                    case 2:
                    case 6:
                        $anim_ID = 0;
                        break;
                    case 3:
                    case 7:
                        $anim_ID = 2;
                        break;
                }
                break;

            case 129: //moth
                $frame_ID = 5;
                break;

            case 110: //caterpillar
                $event->flip_x = true;
                break;

            case 195: //uterus
                $frame = $j2a->get_frame($set_ID, $anim_ID, $frame_ID);
                $frame[1] = imagerotate($frame[1], -90, imagecolorat($frame[1], 0, 0));
                $frame[0] = [
                    'width' => imagesx($frame[1]),
                    'height' => imagesy($frame[1]),
                    'hotspotx' => 0 - (imagesx($frame[1]) / 2),
                    'hotspoty' => 0 - (imagesy($frame[1]) / 2),
                    'coldspotx' => 0,
                    'coldspoty' => 0
                ];
                break;

            case 237: //BeeBoy
                $boy = $j2a->get_frame($set_ID, 0, 0);
                $yob = $boy[1];
                imageflip($yob, IMG_FLIP_HORIZONTAL);
                $beeboy = $this->get_empty_image(3 * 32, 2 * 32);

                imagecopy($beeboy, $boy[1], 26, 19, 0, 0, imagesx($boy[1]), imagesy($boy[1]));
                imagecopy($beeboy, $yob, 62, 23, 0, 0, imagesx($boy[1]), imagesy($boy[1]));
                imagecopy($beeboy, $boy[1], 18, 52, 0, 0, imagesx($boy[1]), imagesy($boy[1]));
                imagecopy($beeboy, $yob, 44, 47, 0, 0, imagesx($boy[1]), imagesy($boy[1]));
                imagecopy($beeboy, $boy[1], 61, 43, 0, 0, imagesx($boy[1]), imagesy($boy[1]));

                $frame[1] = $beeboy;
                $frame[0] = [
                    'width' => imagesx($beeboy),
                    'height' => imagesy($beeboy),
                    'hotspotx' => 0 - (1.5 * 32),
                    'hotspoty' => 0 - 48,
                    'coldspotx' => 0,
                    'coldspoty' => 0
                ];
                break;

            case 235: //bolly boss
                $top = $j2a->get_frame($set_ID, 3, 0);
                $bottom = $j2a->get_frame($set_ID, 2, 0);
                $gun = $j2a->get_frame($set_ID, 6, 0);
                //not gonna bother with the chain, sorry

                $height = $top[0]['height'] + $bottom[0]['height'];
                $bolly = $this->get_empty_image(max($top[0]['width'], $bottom[0]['width']), $height);

                imagecopy($bolly, $top[1], (abs($bottom[0]['hotspotx']) - abs($top[0]['hotspotx'])), 0, 0, 0, imagesx($top[1]), imagesy($top[1]));
                imagecopy($bolly, $bottom[1], 0, $top[0]['height'], 0, 0, imagesx($bottom[1]), imagesy($bottom[1]));
                imagecopy($bolly, $gun[1], 17, $top[0]['height'] + 14, 0, 0, imagesx($gun[1]), imagesy($gun[1]));

                $event->flip_x = true;

                $frame[1] = $bolly;
                $frame[0] = [
                    'width' => imagesx($bolly),
                    'height' => imagesy($bolly),
                    'hotspotx' => 0,
                    'hotspoty' => 0,
                    'coldspotx' => 0,
                    'coldspoty' => 0
                ];
                break;

            case 183: //float lizard
            case 250: //festive float lizard
                $copter = $j2a->get_frame($set_ID, 3, 0);
                $lizard = $j2a->get_frame($set_ID, 2, 0);

                $height = $copter[0]['height'] + $lizard[0]['height'];

                $float_lizard = $this->get_empty_image($lizard[0]['width'], $height);

                $copter_offset = abs($lizard[0]['hotspotx'] - $copter[0]['hotspotx']);
                $lizard_offset = 23; //matching up the hotspots doesn't give the right result. hardcoded?
                imagecopy($float_lizard, $copter[1], $copter_offset, 0, 0, 0, imagesx($copter[1]), imagesy($copter[1]));
                imagecopy($float_lizard, $lizard[1], 0, $lizard_offset, 0, 0, imagesx($lizard[1]), imagesy($lizard[1]));

                $frame[1] = $float_lizard;
                $frame[0] = [
                    'width' => imagesx($float_lizard),
                    'height' => imagesy($float_lizard),
                    'hotspotx' => $copter[0]['hotspotx'],
                    'hotspoty' => $copter[0]['hotspoty'],
                    'coldspotx' => 0,
                    'coldspoty' => 0
                ];
                $event->offset_x += 3; //again, just the hotspot doesn't give the right position?
                unset($float_lizard, $lizard, $copter);
                break;
        }

        if (!isset($frame)) {
            if ($event->draw_mode == JJ2Event::DRAW_CRATE) {
                $frame = $j2a->get_frame_as_crate($set_ID, $anim_ID, $frame_ID, $this->palette, $lut);
            } elseif ($event->draw_mode == JJ2Event::DRAW_MONITOR) {
                $frame = $j2a->get_frame_as_monitor($set_ID, $anim_ID, $frame_ID, $this->palette, $lut);
            } else {
                $frame = $j2a->get_frame($set_ID, $anim_ID, $frame_ID, $this->palette, $lut);
            }
        }

        $event->sprite = $frame;
        $this->event_cache[$event_bytes] = &$event;

        return $event;
    }
}


/**
 * Class JJ2Event
 *
 * @package J2o\Lib
 */
class JJ2Event {
    /**
     * Draw event normally
     */
    const DRAW_NORMAL = 1;
    /**
     * Draw event as a 15-ammo crate
     */
    const DRAW_CRATE = 2;
    /**
     * Draw event as a powerup monitor
     */
    const DRAW_MONITOR = 4;
    /**
     * @var array As returned by `J2AFile::get_frame()`; an array of `[frame info, image resource]`
     */
    public ?array $sprite = NULL;
    /**
     * @var bool Is event a floating pickup?
     */
    public bool $is_pickup = false;
    /**
     * @var bool Is event affected by gravity (i.e. moved towards nearest mask below it)?
     */
    public bool $feels_gravity = false;
    /**
     * @var int  Difficulty; 0 = Normal, 1 = Easy, 2 = Hard, 3 = MP Only
     */
    public int $difficulty = 0;
    /**
     * @var bool Use hotspot for positioning (instead of coldspot)?
     */
    public bool $use_hotspot = true;
    /**
     * @var bool Always adjust position, even if floating?
     */
    public bool $always_adjust = false;
    /**
     * @var int Opacity; level will shine through if < 100
     */
    public int $opacity = 100;
    /**
     * @var int Drawing mode: one of the DRAW_ constants
     */
    public int $draw_mode = 1;
    /**
     * @var int Horizontal offset from default rendering position
     */
    public int $offset_x = 0;
    /**
     * @var int Vertical offset from default rendering position
     */
    public int $offset_y = 0;
    /**
     * @var bool Always draw event flipped horizontally?
     */
    public bool $flip_x = false;
    /**
     * @var bool Always draw event flipped vertically?
     */
    public bool $flip_y = false;
}