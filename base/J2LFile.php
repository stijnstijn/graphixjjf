<?php
/**
 * Level reader - gets information from file and provides methods to render it to an image
 */

namespace J2o\Lib;


use J2o\Exception\JJ2FileException, J2o\Exception\BufferException;


/**
 * Level class
 *
 * Reads and interprets Jazz Jackrabbit 2 Level files
 */
class J2LFile extends JJ2File {
    /**
     * Magic byte value for TSF tilesets
     *
     * @const  int
     */
    const VERSION_TSF = 515;
    /**
     * Magic byte value for 1.23 tilesets
     *
     * @const  int
     */
    const VERSION_123 = 514;

    /**
     * @var int Max size of the image, in pixels. This is set by the constructor depending on the memory available to
     *      PHP. The actual image dimensions will be calculated based on the aspect ratio of the part of layer 4 that
     *      is used by the level, if this 'pixel budget' is exceeded and `get_preview()` is used. If you're calling
     *      `get_image()` directly, you're on your own and need to manually calculate a crop box that keeps the size
     *      manageable, or an exception may be thrown.
     */
    private int $budget;
    /**
     * @var string  Canonical filename, e.g. the one under which this file would be referred to by other files. Is a
     * separate variable because level file names may change sometimes e.g. when they are compiled into level packs.
     */
    private string $canonical_filename;
    /**
     * @var J2TFile  Tileset to use for this level
     */
    private J2TFile $tileset;
    /**
     * @var resource Tileset image
     */
    private $tileset_image = NULL;
    /**
     * @var resource Tileset mask image
     */
    private $tileset_image_mask;
    /**
     * @var resource Level mask image
     */
    private $level_mask;
    /**
     * @var array  Crop box
     */
    private array $box;
    /**
     * @var int Width of the area of the level to render/parse, in pixels
     */
    private int $width;
    /**
     * @var int Height of the area of the level to render/parse, in pixels
     */
    private int $height;
    /**
     * @var array Cached layer settings
     */
    private array $layers;
    /**
     * @var int MLLE version this level was saved with (for extra data) - 0 = no MLLE
     */
    private int $mlle_version = 0;
    /**
     * @var array  MLLE extended settings
     */
    private array $mlle_settings = [];
    /**
     * @var array  File paths of files that may contain extra data requried by the level
     */
    private array $adjacent = [];
    /**
     * @var array  Event IDs to be replaced by other event IDs, as a from => to mapping
     */
    private array $redirect = [];
    /**
     * @var array  Palette remappings for specific sprites, with sprite setIDs as keys and
     * each setID being an array with animIDs as key and a 256-colour palette as value
     */
    private array $palette_remapping = [];
    /**
     * @var array  Supported custom weapons. Keys are IDs, either as references in MLLE's
     * custom weapons code or via se::[ID].setAsWeapon. Values are event IDs for the +3
     * pickup for that weapon.
     */
    private array $supported_weapons = [
        'BubbleGun' => 530,
        'LaserBlaster' => 580,
        'FlashBang' => 560,
        'ArcaneWeapons::MeteorGun' => 610,
        'ArcaneWeapons::CosmicDuster' => 540,
        'ArcaneWeapons::MortarLauncher' => 620,
        'ArcaneWeapons::NailGun' => 630,
        'ArcaneWeapons::TornadoGun' => 670,
        'ArcaneWeapons::LightningRod' => 590,
        'ArcaneWeapons::SanguineSpear' => 660,
        'ArcaneWeapons::FusionCannon' => 570,
        'SzmolWeaponPack::AutoTurret' => 790,
        'SzmolWeaponPack::DischargeGun' => 550,
        'SzmolWeaponPack::LockOnMissile' => 600,
        'SzmolWeaponPack::PetrolBomb' => 640,
        'SzmolWeaponPack::MeleeSword' => 650,
        'SmokeWopens::ElektrekShield' => 760,
        'SmokeWopens::ZeusArtillery' => 770,
        'SmokeWopens::PhoenixGun' => 780,
        'se::EnergyBlasterMLLEWrapper' => 520,
        'se::FireworkMLLEWrapper' => 510,
        'se::RollerMLLEWrapper' => 500,
        'se::MiniMirvMLLEWrapper' => 810,
        'energyBlast' => 520,
        'firework' => 510,
        'roller' => 500,
        'miniMirv' => 810,
        'WeaponVMega::Boomerang' => 680,
        'WeaponVMega::Burrower' => 690,
        'WeaponVMega::IceCloud' => 700,
        'WeaponVMega::Pathfinder' => 710,
        'WeaponVMega::Backfire' => 720,
        'WeaponVMega::Crackerjack' => 730,
        'WeaponVMega::GravityWell' => 740,
        'WeaponVMega::Voranj' => 750,
        'WeaponVMega::Meteor' => 800
    ];
    /**
     * @var array  Array of player names to randomly choose from when rendering
     * multiplayer sprites (these are all creators of the game etc)
     */
    private $player_names = [
        'arjan',
        'michiel',
        'CliffyB',
        'Noogy',
        'AlexanderB',
        'Nickadoo',
        'Robert[AA]',
        'Nando',
        'Spring',
        'Jeh',
        'siren'
    ];


    /**
     * Constructor method
     *
     * Sets up the object, checking whether given file path is valid and giving several
     * variables initial values. Then it reads the header via another method.
     *
     * @throws  JJ2FileException  If there is a problem with the level file.
     *
     * @access  public
     */
    public function initialise(): void {
        if (!is_readable($this->filename)) {
            throw new JJ2FileException('Could not read level file '.$this->filename);
        }

        if (filesize($this->filename) == 0) {
            throw new JJ2FileException('Level file '.$this->filename.' is zero bytes');
        }

        $this->canonical_filename = $this->filename;
        $this->data = file_get_contents($this->filename);
        $this->parse_header();

        //this was calibrated using Blackraptor's "A Generic Single Player Level II"
        $this->budget = floor(get_memory_limit() / 23);
    }

    /**
     * Set canonical filename for level file
     *
     * @param string $canonical_filename The canonical filename, with which it is referred to by other files, if
     * different from the given file name.
     */
    public function set_canonical_filename(string $canonical_filename): void {
        $this->canonical_filename = $canonical_filename;
    }

    /**
     * Override list of player names to use when rendering multiplayer level
     * sprites in the level preview
     *
     * @param array $player_names  Player names, as strings
     */
    public function set_player_names(array $player_names): void {
        $this->player_names = $player_names;
    }

    /**
     * Add player name to names to use when rendering multiplayer level
     * sprites in the level preview
     *
     * @param string $player_name  Player name to add
     */
    public function add_player_name(string $player_name): void {
        $this->player_names[] = $player_name;
    }


    /**
     * Load a list of files that may be used later for looking op external data
     *
     * May be tilesets, other level files, et cetera... only requirement is that their
     * path is readable. How they are handled and parsed is determined by methods that
     * are called later.
     *
     * @param array $paths An array of file paths to load. The paths can be string or
     * arrays. In the latter case, the arrays should have two items; first the
     * *path* (e.g. MySwampsEdit.j2t), second the *canonical name* (e.g. Swamps.j2t).
     *
     * @throws JJ2FileException  If a given file path is not readable.
     */
    public function load_adjacent(array $paths): void {
        foreach ($paths as $path) {
            if (is_array($path)) {
                $canonical = $path[1];
                $path = $path[0];
            } else {
                $canonical = explode('/', $path);
                $canonical = array_pop($canonical);
            }
            if (!is_readable($path)) {
                throw new JJ2FileException('Trying to load adjacent file '.$path.' for J2L file '.$this->filename.', but file is not readable.');
            }

            $this->adjacent[$canonical] = $path;
        }
    }


    /**
     * Find full path for an adjacent file
     *
     * Looks if given file name matches one of the paths of adjacent files loaded
     * earlier - returns full path if found, or NULL if no matches are found.
     *
     * @param string $filename
     *
     * @return string  Path, or NULL if no matches are found.
     */
    public function find_adjacent(string $filename): ?string {
        //ignore any folder shenanigans, which are a potential security risk
        $filename = explode('/', $filename);
        $filename = array_pop($filename);
        $filename = trim(str_replace("\0", '', $filename));

        foreach ($this->adjacent as $canonical_filename => $path) {
            if (strtolower($filename) == strtolower($canonical_filename)) {
                return $path;
            }
        }

        return NULL;
    }


    /**
     * Parse Level header
     *
     * The Level header (first 262 bytes) contains offsets for the actual Level data
     * which is retrieved here, and stored in the offsets array. The header is saved too
     * for later usage.
     *
     * @throws JJ2FileException  If level file cannot be parsed, or is of unknown version
     */
    private function parse_header(): void {
        $raw_header = substr($this->data, 0, self::HEADER_SIZE);
        if (strpos($raw_header, 'MegaGames') === false) { //crude integrity check
            throw new JJ2FileException('Cannot read level file '.$this->filename.'; missing or corrupt file header');
        }

        $header = new BufferReader($raw_header);

        $header->seek(188);
        $this->name = $header->string(32);

        $header->seek(220);
        $this->version = $header->short();

        $header->seek(230);
        $this->substream_sizes = [
            'data1_c' => $header->long(),
            'data1_u' => $header->long(),
            'data2_c' => $header->long(),
            'data2_u' => $header->long(),
            'data3_c' => $header->long(),
            'data3_u' => $header->long(),
            'data4_c' => $header->long(),
            'data4_u' => $header->long()
        ];

        if ($this->version != self::VERSION_TSF && $this->version != self::VERSION_123) {
            throw new JJ2FileException('Unknown version "'.$this->version.'" for level file '.$this->filename);
        }
    }


    /**
     * Get level settings, i.e. parsed Data1 values
     *
     * Parsed result is cached for later re-use
     *
     * @return array  Parsed level info
     */
    public function get_settings(): array {
        if (!$this->settings) {
            $max_tiles = $this->version == self::VERSION_TSF ? 4096 : 1024;
            $anim_tiles = 128; //$this->version == self::VERSION_TSF ? 256 : 128;

            $info = new BufferReader($this->get_substream(1));
            $this->settings = [
                'jcs_offsetx' => $info->short(),
                'security1' => $info->short(),
                'jcs_offsety' => $info->short(),
                'security2' => $info->short(),
                'jcs_layer' => $info->char() & 15,
                'light_min' => $info->char(),
                'light_start' => $info->char(),
                'anim_count' => $info->short(),
                'splitscreen_vertical' => $info->store($anim_tiles)->bool(),
                'is_multiplayer' => $info->bool(),
                'buffer_size' => $info->long(),
                'levelname' => $info->string(32),
                'tileset' => $info->string(32),
                'bonus_level' => $info->string(32),
                'next_level' => $info->string(32),
                'secret_level' => $info->string(32),
                'music' => $info->string(32),
                'strings' => $info->string(512, 16),
                'layers_seq' => [
                    'properties' => $info->long(8),
                    'type' => $info->char(8),
                    'has_tiles' => $info->bool(8),
                    'width' => $info->long(8),
                    'width_real' => $info->long(8),
                    'height' => $info->long(8),
                    'z' => $info->int32(8),
                    'detail' => $info->char(8),
                    'wave_x' => $info->int32(8),
                    'wave_y' => $info->int32(8),
                    'speed_x' => $info->int32(8),
                    'speed_y' => $info->int32(8),
                    'speed_auto_x' => $info->int32(8),
                    'speed_auto_y' => $info->int32(8),
                    'texture_mode' => $info->char(8),
                    'texture_rgbs' => $info->bytes(3, 8)
                ],
                'static_tiles' => $info->short(),
                'tile_events' => $info->long($max_tiles),
                'tile_flipped' => $info->bool($max_tiles),
                'tile_type' => $info->char($max_tiles),
                'tile_used' => $info->char($max_tiles),
                'anim' => ($anim_tiles > 0 ? $info->bytes(137, $anim_tiles) : [])
            ];

            if(!is_array($this->settings['anim'])) {
                $this->settings['anim'] = [$this->settings['anim']];
            }

            //parse anim data
            for ($i = 0; $i < $this->settings['anim_count']; $i++) {
                $anim = new BufferReader($this->settings['anim'][$i]);
                $this->settings['anim'][$i] = [
                    'wait_frame' => $anim->short(),
                    'wait_random' => $anim->short(),
                    'wait_pingpong' => $anim->short(),
                    'pingpong' => $anim->bool(),
                    'speed' => $anim->char(),
                    'frame_count' => $anim->char(),
                    'frames' => $anim->short(64)
                ];
                $this->settings['anim'][$i]['frames'] = array_slice($this->settings['anim'][$i]['frames'], 0, $this->settings['anim'][$i]['frame_count']);
            }

            //flip the layer properties (i.e. not 8 values for each setting, but 8 layers with settings as children)
            $this->settings['layers'] = [];
            foreach ($this->settings['layers_seq'] as $setting => $data) {
                foreach ($data as $i => $value) {
                    $this->settings['layers'][$i + 1][$setting] = $value; //i+1 so we use JCS layer IDs, 1-indexed
                }
            }
            unset($this->settings['layers_seq']);

            //parse some packed layer properties
            for ($i = 1; $i <= 8; $i += 1) {
                $this->settings['layers'][$i]['tile_width'] = (($this->settings['layers'][$i]['properties'] & 1) == 1);
                $this->settings['layers'][$i]['tile_height'] = (($this->settings['layers'][$i]['properties'] & 2) == 2);
                $this->settings['layers'][$i]['limit_visible'] = (($this->settings['layers'][$i]['properties'] & 4) == 4);
                $this->settings['layers'][$i]['texture_mode'] = (($this->settings['layers'][$i]['properties'] & 8) == 8);
                $this->settings['layers'][$i]['texture_stars'] = (($this->settings['layers'][$i]['properties'] & 16) == 16);
                $this->settings['layers'][$i]['texture_rgb'] = [];
                $this->settings['layers'][$i]['name'] = '';
                $this->settings['layers'][$i]['is_sprite'] = ($i == 4);
                for ($j = 0; $j < 3; $j += 1) {
                    $this->settings['layers'][$i]['texture_rgb'][] = ord(substr($this->settings['layers'][$i]['texture_rgbs'], $j, 1));
                }
                unset($this->settings['layers'][$i]['texture_rgbs'], $this->settings['layers'][$i]['properties']);
            }

            $this->layers = $this->settings['layers'];
            $this->settings['layers'] = &$this->layers;
            $settings['palette'] = NULL;

            try {
                $this->load_mlle();
            } catch (JJ2FileException $e) {
                //MLLE data missing, but that can be perfectly okay...
            }

            try {
                $this->load_script();
            } catch (JJ2FileException $e) {
                //No script file, no problem
            }

            $this->determine_sprite_layer();
        }

        return $this->settings;
    }


    /**
     * Load MLLE settings and data
     *
     * Violet's level editor MLLE allows adding extra layers and tilesets to a level file, and
     * saves that data in an extra data stream ("Data5"). This method parses that data, edits the
     * loaded tileset file to include any extra tiles, and adds any extra layers to the level
     * file.
     *
     * Basically, this re-implements MLLE-Include-1.5.asc, in PHP!
     *
     * @throws JJ2FileException  If parsing fails, for some reason (including there being no data to parse)
     */
    public function load_mlle(): void {
        $offset = self::HEADER_SIZE;
        for ($i = 1; $i < 5; $i += 1) {
            $offset += $this->substream_sizes['data'.$i.'_c'];
        }

        if (strlen($this->data) < $offset + 5) {
            throw new JJ2FileException('No Data5 section in level file '.$this->filename);
        }

        $data5 = new BufferReader($this->data);
        $data5->seek($offset);
        if ($data5->string(4) != 'MLLE') {
            throw new JJ2FileException('Data5 exists, but is not MLLE data');
        }

        //only the 1.3 and 1.4 libraries are supported for now
        $version = $data5->uint32();
        if (!in_array($version, [0x103, 0x104, 0x105])) {
            throw new JJ2FileException('Unsupported MLLE version');
        }

        $size_compressed = $data5->uint32();
        $size_uncompressed = $data5->uint32();
        $mlle_data = gzuncompress($data5->bytes($size_compressed));
        if ($mlle_data === false || strlen($mlle_data) != $size_uncompressed) {
            return;
        }

        $data5 = new BufferReader($mlle_data);

        //we're not using most of these but it's clearer than randomly skipping bytes
        $this->mlle_settings = [
            'snow' => $data5->bool(),
            'snow_indoors' => !$data5->bool(),
            'snow_intensity' => $data5->uint8(),
            'snow_type' => $data5->uint8(),
            'warps_transmute' => $data5->bool(),
            'delay_generator' => $data5->bool(),
            'echo' => $data5->int32(),
            'darkness_colour' => $data5->uint32(),
            'water_speed' => $data5->float(),
            'water_antigrav' => $data5->uint8(),
            'water_layer' => $data5->int32(),
            'water_lighting' => $data5->uint8(),
            'water_level' => $data5->float(),
            'water_gradient1' => [$data5->skip(1)->uint8(), $data5->uint8(), $data5->uint8()],
            'water_gradient2' => [$data5->skip(1)->uint8(), $data5->uint8(), $data5->uint8()]
        ];

        //remapped palette - use original tileset palette if not available
        $this->mlle_settings['has_palette'] = $data5->bool();
        $this->mlle_settings['palette'] = [];

        if ($this->mlle_settings['has_palette']) {
            for ($c = 0; $c < 256; $c += 1) {
                $this->mlle_settings['palette'][$c] = [$data5->uint8(), $data5->uint8(), $data5->uint8()];
            }
        } else {
            $this->mlle_settings['palette'] = NULL;
        }

        // remapped event palettes
        // we do this on a per-sprite basis; for each event a number of sprites that are to be recoloured
        // can be defined (more for later versions of MLLE)
        $recolorable_sprites = [
            [[72, 0]], // carrot bump
            [[72, 2]], // 500 bump
            [[17, 0]], // carrotus pole
            [[28, 0]], // diamondus pole
            [[72, 4], [72, 5]], // paddles
            [[58, 0]], // jungle pole
            null, // JJ2+ leaf
            [[74, 0]], // psych pole
            [[84, 0]], // small tree
            null, // snow
            null, // rain
        ];

        if ($version >= 0x105) {
            $recolorable_sprites = array_merge($recolorable_sprites, [
                [[10, 0], [10, 1]], // boll platform
                [[48, 0], [48, 1]], // fruit platform
                [[51, 0], [51, 1]], // grass platform
                [[73, 0], [73, 1]], // pink platform
                [[87, 0], [87, 1]], // sonic platform
                [[95, 0], [95, 1]], // spike platform
                [[93, 0], [93, 1]], // spike boll
                [[93, 0], [93, 1]], // 3d spike boll
                [[106, 1]], // swinging vine
            ]);
        }

        // stored as
        $sprite_palettes = [];
        foreach($recolorable_sprites as $animations) {
            $recolor_animation = $data5->bool();
            if (!$recolor_animation) {
                // no new palette for this event
                continue;
            }

            if(!$animations) {
                // no sprites we will ever render, skip further data for this recolour
                $data5->skip(256);
                continue;
            }

            $remapping = $data5->char(256);
            foreach($animations as $animation) {
                list($setID, $animID) = $animation;
                if(!isset($sprite_palettes[$setID])) {
                    $sprite_palettes[$setID] = [];
                }

                $sprite_palettes[$setID][$animID] = $remapping;
            }
        }
        $this->palette_remapping = $sprite_palettes;


        //load extra tileset data
        //usually we would load the tileset only when rendering, but we need it here for (among other things) the
        //palette data
        $this->load_tileset(NULL, $this->mlle_settings['palette']);
        $this->mlle_settings['extra_tilesets_count'] = $data5->uint8();
        $this->mlle_settings['extra_tilesets'] = [];

        for ($i = 0; $i < $this->mlle_settings['extra_tilesets_count']; $i += 1) {
            $filename = $data5->string_7bit();
            $path = $this->find_adjacent($filename);
            if ($path === NULL) {
                //extra tileset not found, meaning we can't really do anything with the extra layers etc
                throw new JJ2FileException('Could not find tileset file '.$filename);
            }

            $this->mlle_settings['extra_tilesets'][$i] = [
                'file' => $path,
                'tile_start' => $data5->uint16(),
                'tile_count' => $data5->uint16(),
                'has_palette' => $data5->bool()
            ];

            if ($this->mlle_settings['extra_tilesets'][$i]['has_palette']) {
                $this->mlle_settings['extra_tilesets'][$i]['palette'] = [];
                for ($c = 0; $c < 256; $c += 1) {
                    $this->mlle_settings['extra_tilesets'][$i]['palette'][$c] = $this->settings['palette'][$data5->uint8()];
                }
            }
        }

        $extra_layers_count = $data5->uint32();

        //cache layer maps, since we're going to change the order
        for ($i = 1; $i <= 8; $i += 1) {
            $this->get_map($i);
        }

        //load extra layers
        $base_filename = explode('.', $this->canonical_filename);
        array_pop($base_filename);

        $layer_ID = 8;
        if ($extra_layers_count > $layer_ID) {
            for ($i = 1; $i <= floor($extra_layers_count / 8); $i += 1) {
                $external_level = $this->find_adjacent(implode('.', $base_filename).'-MLLE-Data-'.$i.'.j2l');
                if ($external_level === NULL) {
                    throw new JJ2FileException('Cannot find MLLE Data file '.$i);
                }

                try {
                    $external_j2l = new J2LFile($external_level);
                } catch (JJ2FileException $e) {
                    throw new JJ2FileException('Cannot parse MLLE Data file '.$i.' ('.$e->getMessage().')');
                }

                $external_settings = $external_j2l->get_settings();
                foreach ($external_settings['layers'] as $external_layer_ID => $layer) {
                    $layer_ID += 1;
                    $this->import_layer($external_j2l, $external_layer_ID, $layer_ID);
                }
                unset($external_j2l);
            }
        }

        //update layer order
        $new_layers = [];
        $new_ID = 1;
        $imported_layer_ID = 9;
        for ($i = 0; $i < $extra_layers_count; $i += 1) {
            $layer_ID = $data5->char();
            if ($layer_ID != 0xFF) {
                $new_layers[$new_ID] = $this->settings['layers'][$layer_ID + 1];
            } else {
                $new_layers[$new_ID] = $this->settings['layers'][$imported_layer_ID];
                $imported_layer_ID += 1;
            }

            $new_layers[$new_ID]['name'] = $data5->string_7bit();
            $new_layers[$new_ID]['has_tiles'] = !$data5->bool();
            $new_layers[$new_ID]['sprite_mode'] = $data5->uint8();
            $new_layers[$new_ID]['sprite_param'] = $data5->uint8();
            $new_layers[$new_ID]['angle'] = $data5->int32();
            $new_layers[$new_ID]['angle_multiplier'] = $data5->int32();

            $new_ID += 1;
        }

        //save new layer order and map cache
        $this->layers = $new_layers;

        //find total number of tiles added to tileset
        $extra_tiles = 0;
        array_walk($this->mlle_settings['extra_tilesets'], function ($tileset) use (&$extra_tiles) {
            $extra_tiles += $tileset['tile_count'];
        });

        if ($extra_tiles > 0) {
            //expand tileset images, based on parsed info (i.e. amount of imported tiles)
            $tileID = (imagesy($this->tileset_image) / 32) * 10;
            $revised_height = imagesy($this->tileset_image) + (ceil($extra_tiles / 10) * 32);

            $revised_tileset_image = imagecreatetruecolor(320, $revised_height);
            imagefill($revised_tileset_image, 0, 0, $this->tileset->transparent);
            imagecopy($revised_tileset_image, $this->tileset_image, 0, 0, 0, 0, imagesx($this->tileset_image), imagesy($this->tileset_image));

            $revised_mask_image = imagecreatetruecolor(320, $revised_height);
            imagefill($revised_mask_image, 0, 0, $this->tileset->transparent);
            imagecopy($revised_mask_image, $this->tileset_image_mask, 0, 0, 0, 0, imagesx($this->tileset_image_mask), imagesy($this->tileset_image_mask));

            //copy tiles from extra tilesets to main tileset image
            foreach ($this->mlle_settings['extra_tilesets'] as $tileset) {
                try {
                    $j2t = new J2TFile($tileset['file']);
                    if ($tileset['has_palette']) {
                        $j2t->load_palette($tileset['palette']);
                    } else {
                        $j2t->load_palette($this->settings['palette']);
                    }

                    $extra_tileset_image = $j2t->get_image();
                    $extra_mask_image = $j2t->get_image_mask();

                    for ($i = $tileset['tile_start']; $i < ($tileset['tile_start'] + $tileset['tile_count']); $i += 1) {
                        $main_x = ($tileID % 10) * 32;
                        $main_y = floor($tileID / 10) * 32;
                        $extra_x = ($i % 10) * 32;
                        $extra_y = floor($i / 10) * 32;

                        imagecopy($revised_tileset_image, $extra_tileset_image, $main_x, $main_y, $extra_x, $extra_y, 32, 32);
                        imagecopy($revised_mask_image, $extra_mask_image, $main_x, $main_y, $extra_x, $extra_y, 32, 32);
                        $tileID += 1;
                    }
                } catch (JJ2FileException $e) {
                    throw new JJ2FileException('Cannot parse extra tileset '.$tileset['file'].' ('.$e->getMessage().')');
                }
            }
        } else {
            $revised_tileset_image = $this->tileset_image;
            $revised_mask_image = $this->tileset_image_mask;
        }

        //parse tiles changed by user in MLLE
        for ($i = 0; $i < 2; $i += 1) {
            if ($i == 0) {
                $image = &$revised_tileset_image;
            } else {
                $image = &$revised_mask_image;
            }

            $extra_tiles = $data5->uint16();
            for ($tile = 0; $tile < $extra_tiles; $tile += 1) {
                $tileID = $data5->uint16();
                $main_x = ($tileID % 10) * 32;
                $main_y = floor($tileID / 10) * 32;

                for ($pixel = 0; $pixel < 1024; $pixel += 1) {
                    $pixel_x = $main_x + ($pixel % 32);
                    $pixel_y = $main_y + floor($pixel / 32);
                    $color = $data5->uint8();

                    if ($color == 0) {
                        imagealphablending($image, false);
                        imagesetpixel($image, $pixel_x, $pixel_y, $this->tileset->transparent);
                        imagealphablending($image, true);
                    } else {
                        imageputpixel($image, $pixel_x, $pixel_y, $this->settings['palette'][$color][0], $this->settings['palette'][$color][1], $this->settings['palette'][$color][2]);
                    }
                }
            }
        }

        $this->tileset_image = &$revised_tileset_image;
        $this->tileset_image_mask = &$revised_mask_image;

        //load weapons settings (this is only valid for version 0x105+)
        $this->mlle_settings['weapons'] = [];

        if ($version >= 0x105) {
            for ($i = 1; $i <= 9; $i += 1) {
                $weapon = [
                    'custom' => $data5->bool(),
                    'maximum' => $data5->int32(),
                    'comesFromBirds' => $data5->bool(),
                    'comesFromBirdsPowerup' => $data5->skip(-1)->uint8() == 2,
                    'comesFromGunCrates' => $data5->bool(),
                    'gemsLost' => $data5->uint8(),
                    'gemsLostPowerup' => $data5->uint8(),
                    'infinite' => ($data5->uint8() & 1 == 1),
                    'replenishes' => ($data5->skip(-1)->uint8() & 2 == 2),
                    'crate_meta_ID' => NULL,
                    'crate_event_ID' => NULL
                ];

                //MLLE can indicate that an arbitrary event will serve as the gun crate
                //for these guns
                if ($i >= 7) {
                    $crate_event_ID = $data5->uint8();
                    if ($crate_event_ID > 32) {
                        //uses 'meta events' in the event map starting at ID 300
                        $weapon['crate_meta_ID'] = 293 + $i;
                        $weapon['crate_event_ID'] = $crate_event_ID;
                    }
                }

                if ($weapon['custom']) {
                    //this is a bunch of MLLE data we don't need
                    //it does potentially contain weapon parameters, but implementing
                    //all of those is left as an exercise to the reader here
                    $data5->string_7bit(); // weapon name, not needed
                    $weapon_blob_size = $data5->int32();
                    $data5->skip($weapon_blob_size);
                } elseif ($i == 8) {
                    //this is a bunch of stuff we don't do anything with
                    $spread = $data5->uint8();
                    if ($spread == 0) {
                        $weapon['spread'] = 'gun8';
                    } elseif ($spread == 1) {
                        $weapon['spread'] = 'pepperspray';
                    } elseif ($spread >= 2) {
                        $weapon['spread'] = 'normal';
                    }
                    $weapon['gradualAim'] = ($spread == 2);
                }

                $this->mlle_settings['weapons'][$i] = $weapon;
            }
        }


        $this->determine_sprite_layer();
        $this->mlle_version = $version;
    }

    /**
     * Load and parse j2as script
     *
     * For now only scans the script for custom weapon activation and if found
     * sets event redirects accordingly
     */
    protected function load_script() {
        //parse script
        $script_file = $this->find_adjacent(preg_replace('/\.j2l$/', '.j2as', $this->canonical_filename));
        if (!$script_file) {
            return;
        }

        $script = file_get_contents($script_file);
        $weapons_mapping = [];

        /**
         * MLLE weapons: defined at the start of the level in an array, with one value for each weapon,
         * 'null' if unchanged
         */
        $mlle_weapons = preg_match_all('/array<MLLEWeaponApply@> = {([^}]+)}/U', $script, $weapon_def);
        if ($mlle_weapons) {
            $weapon_def = explode(',', $weapon_def[1][0]);

            for ($weapon_index = 0; $weapon_index <= 8; $weapon_index += 1) {
                if ($weapon_index > count($weapon_def)) {
                    break;
                }

                if ($weapon_def[$weapon_index] == 'null') {
                    continue;
                }

                $replacement_weapon = preg_split('/(::Weapon|\(\))/', trim($weapon_def[$weapon_index]));
                $replacement_weapon = $replacement_weapon[0];
                if (!array_key_exists($replacement_weapon, $this->supported_weapons)) {
                    continue;
                }

                $weapons_mapping[$weapon_index] = $replacement_weapon;
            }
        }

        /**
         * SE Weapons; follow a predictable [weapon interface].setAsWeapon(index) pattern which can
         * be extracted
         */
        $se_weapons = preg_match_all('/se::([^.]+)\.setAsWeapon\(([0-9]+)/', $script, $weapon_def);
        if ($weapon_def) {
            for ($i = 0; $i < $se_weapons; $i += 1) {
                $replacement_weapon = $weapon_def[1][$i];
                $weapon_index = intval($weapon_def[2][$i]) - 1;
                if ($weapon_index < 9 && array_key_exists($replacement_weapon, $this->supported_weapons)) {
                    $weapons_mapping[$weapon_index] = $replacement_weapon;
                }
            }
        }

        foreach ($weapons_mapping as $weapon_index => $replacement) {
            $to_ID = $this->supported_weapons[$replacement];

            //bouncer and freezer are out of order in the events list...

            if ($weapon_index == 0) { //blaster
                $this->redirect[131] = $to_ID + 2; //blaster powerup
            } elseif ($weapon_index == 1) { //bouncer
                $this->redirect[34] = $to_ID;
                $this->redirect[54] = $to_ID + 1;
                $this->redirect[132] = $to_ID + 2;
            } elseif ($weapon_index == 2) { //freezer
                $this->redirect[33] = $to_ID;
                $this->redirect[53] = $to_ID + 1;
                $this->redirect[133] = $to_ID + 2;
            } elseif ($weapon_index < 6) {
                $this->redirect[$weapon_index + 32] = $to_ID; // +3 ammo
                $this->redirect[$weapon_index + 52] = $to_ID + 1; // +15 ammo
                $this->redirect[$weapon_index + 131] = $to_ID + 2; // powerup
            } elseif ($weapon_index == 6) { //tnt
                $this->redirect[38] = $to_ID;
                $this->redirect[300] = $to_ID + 1;
                $this->redirect[219] = $to_ID + 2;
            } elseif ($weapon_index == 7) { //pepper/gun8
                $this->redirect[39] = $to_ID;
                $this->redirect[301] = $to_ID + 1;
                $this->redirect[220] = $to_ID + 2;
            } elseif ($weapon_index == 8) { //electro/gun9
                $this->redirect[40] = $to_ID;
                $this->redirect[302] = $to_ID + 1;
                $this->redirect[221] = $to_ID + 2;
            }

        }
    }


    /**
     * Determine which layer is the sprite layer
     *
     * Important because the sprite layer is used as a reference for determining the visible part of the level, etc.
     */
    protected function determine_sprite_layer(): void {
        $settings = $this->get_settings();
        foreach ($settings['layers'] as $i => $layer) {
            if ($layer['is_sprite']) {
                $this->settings['sprite_layer'] = $i;
            }
        }
    }


    /**
     * Load tileset file to use for rendering this level
     *
     * @param string $path Path to tileset file. If left empty, tries to figure out the path on its own
     * @param array $palette Optional; palette to use for the tileset (if not given, J2T's own palette is used)
     *
     * @throws JJ2FileException If tileset file could not be read or parsed
     */
    public function load_tileset(string $path = NULL, array $palette = NULL): void {
        if($this->tileset_image) {
            return;
        }

        //try to guess the path
        if ($path === NULL) {
            $settings = $this->get_settings();
            $path = $this->find_adjacent($settings['tileset']);
            if ($path === NULL) {
                $path = $settings['tileset'];
            }
        }

        if (!is_readable($path)) {
            throw new JJ2FileException('Could not read tileset file '.$path);
        }

        try {
            $tileset = new J2TFile($path);
            if ($palette !== NULL) {
                $tileset->load_palette($palette);
            }
            $this->tileset = &$tileset;

            $this->tileset_image = $this->tileset->get_image();
            $this->tileset_image_mask = $this->tileset->get_image_mask();
            $this->settings['palette'] = $this->tileset->get_palette();
        } catch (JJ2FileException $e) {
            throw new JJ2FileException('Could not load tileset file '.$path.': '.$e->getMessage());
        }
    }


    /**
     * Check if tileset for this level is loaded
     *
     * @return bool  Has `load_tileset` been called (succefully)?
     */
    public function has_tileset(): bool {
        return isset($this->tileset) && $this->tileset instanceof J2TFile;
    }


    /**
     * Add layer from other level to this level
     *
     * Re-arranges existing layers to make room for the new one
     *
     * @param J2LFile $source_j2l Source level file
     * @param int $layerID Layer ID to be imported from external level
     * @param int $insertID Layer ID in this level the imported layer should get
     */
    protected function import_layer(J2LFile &$source_j2l, int $layerID, int $insertID): void {
        $source_settings = $source_j2l->get_settings();

        //make room for layer that is to be imported
        for ($i = count($this->layers) - 1; $i > $insertID; $i -= 1) {
            $this->layers[$i + 1] = $this->layers[$i];
        }

        //calculate the tile map in advance
        $source_j2l->get_map($layerID);

        $source_settings['layers'][$layerID]['is_sprite'] = false; //imported layers can never be the sprite layer
        $this->layers[$insertID] = $source_settings['layers'][$layerID];
    }


    /**
     * Find part of level that has tiles in it
     *
     * Generates a crop box that starts at the upper left corner of the part of layer 4 with tiles in it, skipping
     * anything empty space at the left or top of the level.
     *
     * @return array  Crop coordinates, [[x1, y1], [x2, y2]]
     */
    public function get_visible_box(): array {
        $this->get_settings();
        $layer4 = &$this->layers[$this->settings['sprite_layer']];

        $start_x = $start_y = PHP_INT_MAX;
        $end_x = $end_y = 0;
        $completely_empty = true;

        //set viewable area boundaries based on whether words consist of empty tiles
        $i = 0;
        foreach ($this->get_map($this->settings['sprite_layer']) as $tile_ID) {
            if ($tile_ID['tile'] != 0) {
                $x = ($i % $layer4['width']) * 32;
                $y = floor($i / $layer4['width']) * 32;
                $completely_empty = false;
                $start_x = min($start_x, $x);
                $start_y = min($start_y, $y);
                $end_x = max($end_x, $x + 128);
                $end_y = max($end_y, $y + 32);
            }
            $i += 1;
        }


        if ($completely_empty) {
            //whatever
            return [[0, 0], [min(800, $layer4['width'] * 32), min(600, $layer4['height'] * 32)]];
        } else {
            //put some margin around the area, which will be prettier
            $margin = 64;
            $start_x = max(0, $start_x - $margin);
            $end_x = min($layer4['width'] * 32, $end_x + $margin);
            $start_y = max(0, $start_y - $margin);
            $end_y = min($layer4['height'] * 32, $end_y + $margin);

            return [[$start_x, $start_y], [min($end_x, $layer4['width'] * 32), min($end_y, $layer4['height'] * 32)]];
        }
    }


    /**
     * Find random start position in level and return its pixel coordinates
     *
     * Loops through all start positions found in the level and returns one at random. If none are found, the default
     * position close to the upper left corner is returned.
     *
     * @return array  Start position and rabbit, as an `[[x, y], rabbit]` array (pixel coordinates)
     */
    public function get_start_pos(): array {
        $this->get_settings();
        $layer4 = &$this->layers[$this->settings['sprite_layer']];
        $tiles = $layer4['width'] * $layer4['height'];

        //events are stored per-tile
        $positions = [];
        $events = new BufferReader($this->get_substream(2));
        for ($i = 0; $i < $tiles; $i += 1) {
            $event_ID = $events->long() & 255; //event ID = first 8 bits

            if (in_array($event_ID, [29, 30, 31, 32])) {
                $rabbit = [29 => 'jazz', 30 => 'spaz', 31 => ['jazz', 'spaz', 'lori'][rand(0,2)], 32 => 'lori'][$event_ID];
                $x = ($i % $layer4['width']) * 32;
                $y = floor($i / $layer4['width']) * 32;
                $positions[] = [[$x, $y], $rabbit];
            }
        }

        $position = count($positions) > 0 ? $positions[array_rand($positions)] : [[96, 32], 'jazz'];

        return $position;
    }


    /**
     * Get words
     *
     * Converts the raw word dictionary into a usable format for parsing the
     * tile cache. Result is cached for later re-use.
     *
     * @return array An array of words, each word being an array of four tile IDs
     */
    private function read_words(): array {
        if (!isset($this->words)) {
            $dictionary = $this->get_substream(3);
            $settings = $this->get_settings();

            //get dictionary words (i.e. the tiles a word consists of)
            $dictionary_words = [];
            $dict = new BufferReader($dictionary);
            while (true) {
                try {
                    $word = $dict->bytes(8);
                } catch (BufferException $e) {
                    break;
                }

                $word_tiles = [];
                $word_bytes = new BufferReader($word);
                for ($i = 0; $i < 4; $i += 1) {
                    $tile_ID = $word_bytes->short();
                    $tile_ID = $this->parse_tile_ID($tile_ID);
                    $tile_ID['translucent'] = ($settings['tile_type'][$tile_ID['tile']] == 1);
                    $tile_ID['invisible'] = ($settings['tile_type'][$tile_ID['tile']] == 3);
                    $word_tiles[$i] = $tile_ID;
                }

                $dictionary_words[] = $word_tiles;
            }

            $this->words = $dictionary_words;
        }

        return $this->words;
    }


    /**
     * Parse tile ID as found in level dictionary into tileset tile ID
     *
     * Accounts for animated and flipped tiles. Animated tiles can be nested.
     *
     * @param int $tile_ID Tile ID
     *
     * @return array  Tile info, structure `['tile' => int, 'flipped' => bool, 'animated' => bool]`
     */
    private function parse_tile_ID(int $tile_ID): array {
        $settings = $this->get_settings();
        $max_tiles = ($this->version == self::VERSION_TSF) ? 4096 : 1024;

        $is_animated = false;
        $is_flipped = ($tile_ID & $max_tiles) != 0;
        $is_vflipped = ($tile_ID & 0x2000) != 0;

        $tile_ID %= $max_tiles; //strip flip bits

        if ($tile_ID >= $settings['static_tiles']) {
            $tile_ID = $this->parse_tile_ID($settings['anim'][$tile_ID - $settings['static_tiles']]['frames'][0]);
            $tile_ID = $tile_ID['tile'];
            $is_animated = true;
        }

        return [
            'tile' => $tile_ID,
            'flipped' => $is_flipped,
            'vflipped' => $is_vflipped,
            'animated' => $is_animated
        ];
    }


    /**
     * Expand and repeat a word map in (up to) four directions
     *
     * @param array $map Tile map
     * @param int $width Map width in tiles, before expanding
     * @param int $height Map height in tiles, before expanding
     * @param int $top Rows to add to the top of the map
     * @param int $right Columns to add to the right of the map
     * @param int $bottom Rows to add to the bottom of the map
     * @param int $left Columns to add to the left of the map
     *
     * @return array  Expanded word map
     * @throws JJ2FileException If parameters are incorrect
     */
    public function expand_map(array $map, int $width, int $height, int $top = 0, int $right = 0, int $bottom = 0, int $left = 0): array {
        if (count($map) != $width * $height) {
            throw new JJ2FileException('Cannot expand map: product of width ('.$width.') and height ('.$height.') does not match map size ('.count($map).')');
        }

        $rows = array_chunk($map, $width);

        foreach ($rows as $y => $row) {
            for ($i = 0; $i < $right; $i += 1) {
                $rows[$y][] = $row[$i % $width];
            }
        }

        foreach ($rows as $y => $row) {
            for ($i = 0; $i < $left; $i += 1) {
                array_unshift($rows[$y], $rows[$y][$width - 1]);
            }
        }

        for ($i = 0; $i < $bottom; $i += 1) {
            $rows[] = $rows[$i % $height];
        }

        for ($i = 0; $i < $top; $i += 1) {
            array_unshift($rows, $rows[$height - 1]);
        }

        $result = [];
        foreach ($rows as $row) {
            foreach ($row as $word) {
                $result[] = $word;
            }
        }

        return $result; //call_user_func_array('array_merge', $rows);
    }


    /**
     * Get tile map
     *
     * The tile map is contained in substream 4 and is a list of word IDs, each word being a set of 4 tiles, laid out
     * in a left-to-right pattern (so the top row of the level first, then the next one, et cetera).
     *
     * @param int $layer_ID Layer ID to get tile map for
     *
     * @return array  Array of tile IDs
     * @throws JJ2FileException  If the layer is supposed to have tiles, but no tile data is available
     */
    public function get_map(int $layer_ID): array {
        $settings = $this->get_settings();

        if (!$this->layers[$layer_ID]['has_tiles']) {
            $this->layers[$layer_ID]['map'] = [];
        } elseif (!isset($this->layers[$layer_ID]['map'])) {
            //skip layers before this one that we don't need right now
            $offset = 0;
            for ($i = 1; $i < $layer_ID; $i++) {
                $l = $settings['layers'][$i];
                $l_width_key = $l['tile_width'] ? 'width_real' : 'width';
                $offset += !$l['has_tiles'] ? 0 : 2 * (ceil($l[$l_width_key] / 4) * $l['height']);
            }

            $width_key = $this->layers[$layer_ID]['tile_width'] ? 'width_real' : 'width';
            $word_count = ceil($this->layers[$layer_ID][$width_key] / 4) * $this->layers[$layer_ID]['height'];

            $bytes = new BufferReader($this->get_substream(4));
            $bytes->seek($offset);
            $map = $bytes->short($word_count);

            $map = is_array($map) ? $map : [$map];

            $words = $this->read_words();
            $inflated_map = [];
            foreach ($map as $word) {
                foreach ($words[$word] as $tileID) {
                    $inflated_map[] = $tileID;
                }
            }

            unset($map, $words);

            if(!$inflated_map && $this->layers[$layer_ID]['has_tiles']) {
                throw new JJ2FileException('Layer '.$layer_ID.' is marked as having tiles but map size is 0 after inflation');
            }

            $this->layers[$layer_ID]['map'] = $inflated_map;
        }

        return $this->layers[$layer_ID]['map'];
    }


    /**
     * Get preview image
     *
     * This generates a "real" preview image, scaled, using the actual tileset image. All layers with both x and y
     * speed 1 are rendered on top of each other, in reverse order, so the level looks more or less like what it would
     * like like in-game.
     *
     * @param mixed $layers Which layer to render. Can be either an integer or an array; if it is an array,
     *                                each element should be n with 8 >= n > 0. By default all layers are rendered.
     * @param boolean $render_events Whether to render event sprites as well.
     * @param array|null $box Bounding box within the level to render, as pixel coordinates [[x, y], [x, y]]
     *
     * @return  resource  An image resource, to be used with for example imagepng().
     *
     * @throws  JJ2FileException  If the tileset for the level could not be loaded
     */
    public function get_image($layers = [], bool $render_events = true, array $box = NULL) {
        $settings = $this->get_settings();
        $layer4 = &$this->layers[$settings['sprite_layer']];

        if ($box === NULL) {
            $box = [[0, 0], [$layer4['width'] * 32, $layer4['height'] * 32]];
        } elseif (count($box) != 2 || count($box[0]) != 2 || count($box[1]) != 2 || $box[0][0] > $box[1][0] || $box[0][1] > $box[1][1] || $box[0][0] < 0 || $box[0][1] < 0 || $box[1][0] < 0 || $box[1][1] < 0) {
            throw new JJ2FileException('Invalid crop box dimensions');
        }

        $this->width = $box[1][0] - $box[0][0];
        $this->height = $box[1][1] - $box[0][1];
        $this->box = $box;
        if ($this->width * $this->height > $this->budget) {
            throw new JJ2FileException('Requested image dimensions exceed pixel budget');
        }

        $image = imagecreatetruecolor($this->width, $this->height);

        if($layers === NULL) {
            return $image;
        }
        elseif ($layers === []) {
            $layers = array_reverse(array_keys($this->settings['layers']));
        } else {
            $layers = array_unique($layers);
        }

        if (!$this->has_tileset()) {
            $this->load_tileset();
        }

        $have_rendered_a_layer = false;
        $have_rendered_sprite_layer = false;
        foreach ($layers as $layer_ID) {
            $layer = &$this->layers[$layer_ID];
            if(!$layer['has_tiles']) {
                continue;
            }

            if ($layer['texture_mode'] > 0 && !$have_rendered_sprite_layer) {
                $this->render_textured_background($layer_ID, $image, $layer['texture_rgb']);
                $have_rendered_a_layer = true;
            } elseif (
                !$have_rendered_a_layer
                || ($layer['speed_x'] == 65536 && $layer['speed_x'] == $layer['speed_y'])
                || ($layer['tile_width'] && $layer['tile_height'] && !($have_rendered_sprite_layer && $layer['speed_x'] == 0 && $layer['speed_y'] == 0))
            ) {
                //render only layers with x and y speed = 1 OR (tileX and tileY AND speeds != 0)
                if(!$have_rendered_a_layer && ($layer['width'] != $layer['height'] || $layer['width'] > 8)) {
                    //some special treatment for the background layer if it's not tiling so well
                    $this->render_stretched_layer($layer_ID, $image);
                } else {
                    $this->render_layer($layer_ID, $image, false, $box);
                }

                $have_rendered_a_layer = true;

                if ($layer_ID == $settings['sprite_layer']) {
                    $have_rendered_sprite_layer = true;
                    if ($render_events) {
                        $this->load_level_masks();
                        $image = $this->render_events($image);
                    }
                }

            }
        }

        return $image;
    }


    /**
     * Load tileset masks
     *
     * @throws JJ2FileException  If no tileset is available
     */
    private function load_level_masks(): void {
        if (!$this->has_tileset()) {
            throw new JJ2FileException('You need to load a tileset before generating a tileset image');
        }

        $settings = $this->get_settings();

        $sprite_layer = $this->layers[$this->settings['sprite_layer']];
        $this->level_mask = imagecreatetruecolor($sprite_layer['width'] * 32, $sprite_layer['height'] * 32);
        $this->level_mask = $this->render_layer($settings['sprite_layer'], $this->level_mask, true, [[0, 0], [imagesx($this->level_mask), imagesy($this->level_mask)]]);
    }


    /**
     * Render JJ2-style Warp Horizon textured background
     *
     * @param int $layer_ID Layer to render as textured background
     * @param resource $level_image Image to render to
     * @param array $fade_color Fade colour, in `[r, g, b]` format
     *
     * @return resource  Rendered background GD image resource
     */
    private function render_textured_background(int $layer_ID, $level_image, array $fade_color = [0, 0, 0]) {
        $this->get_settings();
        $layer = &$this->layers[$layer_ID];

        //this resolution is the largest that doesn't have noticeable slowdown, experiments show
        $screen = imagecreatetruecolor(1600, 1200);
        $texture = imagecreatetruecolor($layer['width'] * 32, $layer['height'] * 32);
        $this->render_layer($layer_ID, $texture, false, [[0, 0], [imagesx($texture), imagesy($texture)]]);

        //Warp Horizon effect - I don't pretent to understand (or to have tried to understand) what it does, thanks to
        //Violet for a reference implementation that I copied
        $offset = rand(0, imagesx($texture));
        $half_screen_width = imagesx($screen) / 2;
        for ($y = 0; $y < imagesy($screen); $y += 1) {
            $distance_from_middle = $y - (imagesy($screen) / 2);
            $ref = 60 / (abs($distance_from_middle) + 8);
            $texture_y = imagesy($texture) * $ref * $distance_from_middle / 8;
            for ($x = 0; $x < imagesx($screen); $x += 1) {
                $texture_x = imagesx($texture) * ($ref * ($x - $half_screen_width)) / 256;
                $texture_x += $offset;
                $color = imagecolorat($texture, abs($texture_x) % imagesx($texture), abs($texture_y) % imagesy($texture));
                imagesetpixel($screen, $x, $y, imagecolorallocate($screen, ($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF));
            }
        }

        //Fade effect
        imagealphablending($screen, true);
        $fade_start = imagesy($screen) / 4;
        $fade_end = imagesy($screen) - $fade_start;
        $fade_step = M_PI / ($fade_end - $fade_start);
        $fade = 0;

        for ($y = $fade_start; $y < $fade_end; $y += 1) {
            $fade += $fade_step;
            $alpha = 127 * (1 - sin($fade));
            $color = imagecolorallocatealpha($screen, $fade_color[0], $fade_color[1], $fade_color[2], $alpha);
            imagefilledrectangle($screen, 0, $y, imagesx($screen), $y, $color);
        }

        //resample to full image size
        imagecopyresampled($level_image, $screen, 0, 0, 0, 0, imagesx($level_image), imagesy($level_image), imagesx($screen), imagesy($screen));

        return $screen;
    }

    /**
     * Render a layer once and make it fill the target image
     *
     * This can be used for non-tiling background layers. Having a stretched and potentially blurry background image
     * is usually preferable to trying to tile a non-tiling image.
     *
     * Does try to keep the proportions of the image though.
     *
     * @param int $layer_ID  Layer ID to stretch and render
     * @param resource $image  Image to render to
     */
    private function render_stretched_layer(int $layer_ID, $image) {
        $layer = $this->layers[$layer_ID];

        $texture = imagecreatetruecolor($layer['width'] * 32, $layer['height'] * 32);
        $this->render_layer($layer_ID, $texture, false, [[0, 0], [imagesx($texture), imagesy($texture)]], false);

        // see if the texture tiles: if it does, render the level as usual
        // if not, stretch it
        $diff = [];
        for($x = 0; $x < imagesx($texture); $x += 1) {
            $c1 = imagecolorat($texture, $x, 0);
            $c1 = [($c1 >> 16) & 0xFF, ($c1 >> 8) & 0xFF, $c1 & 0xFF];
            $c2 = imagecolorat($texture, $x, imagesy($texture) - 1);
            $c2 = [($c2 >> 16) & 0xFF, ($c2 >> 8) & 0xFF, $c2 & 0xFF];
            $diff[] = array_sum([abs($c1[0] - $c2[0]), abs($c1[1] - $c2[1]), abs($c1[2] - $c2[2])]);
        }

        for($y = 0; $y < imagesy($texture); $y += 1) {
            $c1 = imagecolorat($texture, 0, $y);
            $c1 = [($c1 >> 16) & 0xFF, ($c1 >> 8) & 0xFF, $c1 & 0xFF];
            $c2 = imagecolorat($texture, imagesx($texture) - 1, $y);
            $c2 = [($c2 >> 16) & 0xFF, ($c2 >> 8) & 0xFF, $c2 & 0xFF];
            $diff[] = array_sum([abs($c1[0] - $c2[0]), abs($c1[1] - $c2[1]), abs($c1[2] - $c2[2])]);
        }

        $avg_diff = array_sum($diff) / count($diff);
        if($avg_diff < 100) {
            // sort of arbitrary, but this seems to be a reasonable threshold
            // if the layer tiles, don't stretch but render as usual
            $this->render_layer($layer_ID, $image, false, $this->box);
            return;
        }

        $ratio_texture = imagesx($texture) / imagesy($texture);
        $ratio_target = imagesx($image) / imagesy($image);

        // preserve aspect ratio of layer while stretching
        if($ratio_target > $ratio_texture) {
            $paste_width = imagesx($image);
            $paste_height = imagesx($image) / $ratio_texture;
            $paste_x = 0;
            $paste_y = 0 - ($paste_height - imagesy($image)) / 2;
        } else {
            $paste_width = imagesy($image) * $ratio_texture;
            $paste_height = imagesy($image);
            $paste_x = 0 - ($paste_width - imagesx($image)) / 2;
            $paste_y = 0;
        }

        imagecopyresampled($image, $texture, $paste_x, $paste_y, 0, 0, $paste_width, $paste_height, imagesx($texture), imagesy($texture));
        unset($texture);
    }


    /**
     * Render a layer
     *
     * Renders a layer to the level preview image.
     *
     * @param int $layer_ID The layer to render.
     * @param resource $image The image to render to. May contain background layers to draw on top of!
     * @param boolean $is_mask Are we rendering the layer as a mask?
     * @param array|NULL $box Crop box; if left NULL, the object's `$box` field is used.
     * @param bool $expand  Auto-expand layer map if it tiles
     *
     * @return  resource The level preview image.
     * @throws JJ2FileException  If the layer to be rendered has no tiles in it
     */
    private function render_layer(int $layer_ID, $image, bool $is_mask = false, array $box = NULL, $expand=true) {
        $this->get_settings();
        $layer = $this->layers[$layer_ID];
        if(!$layer['has_tiles']) {
            throw new JJ2FileException('Cannot render layer '.$layer_ID.'; has no tiles');
        }

        $layer4 = &$this->layers[$this->settings['sprite_layer']];

        if ($box === NULL) {
            $box = $this->box;
        }

        $offset_x = 0 - $box[0][0];
        $offset_y = 0 - $box[0][1];

        $width_key = $layer['tile_width'] ? 'width_real' : 'width';

        //get tile map for this layer
        $map = $this->get_map($layer_ID);

        //if the layer is tiled in both directions, do so with the middle of the level as point of origin - that
        //minimizes drift in either direction (but not if both speeds are 1)
        $expand_horizontal = $expand_vertical = 0;
        if ($layer['tile_width'] && $layer[$width_key] < $layer4['width'] && $layer['speed_x'] != 65536) {
            $expand_horizontal = max(0, $layer4['width'] - $layer[$width_key]) / 2;
        }

        if ($layer['tile_height'] && $layer['height'] < $layer4['height'] && $layer['speed_y'] != 65536) {
            $expand_vertical = max(0, $layer4['height'] - $layer['height']) / 2;
        }

        if(!$map) {
            //there have been levels with layers that had has_tiles = true
            //but also had no tiles (e.g. HH18_Level04.j2l)... not sure what's
            //up with those
            return $image;
        }

        if ($expand && ($expand_horizontal > 0 || $expand_vertical > 0)) {
            $map = $this->expand_map($map, $layer['width_real'], $layer['height'], floor($expand_vertical), ceil($expand_horizontal), ceil($expand_vertical), floor($expand_horizontal));
            $layer['width_real'] += floor($expand_horizontal) + ceil($expand_horizontal);
            $layer['height'] += floor($expand_vertical) + ceil($expand_vertical);
            $real_width = $layer['width_real'] * 32;
        } else {
            //if the level width is not a multiple of 4, using it for positioning will mess stuff up, so adjust
            $real_width = ceil($layer[$width_key] / 4) * 4 * 32;
        }

        $i = 0;

        //make sure everything goes right wrt transparency and overlapping
        imagealphablending($image, true);

        if ($is_mask) {
            //keep an empty tile handy if we're rendering masks, since we're gonna need it a lot
            $empty_tile = imagecreatetruecolor(32, 32);
            imagefill($empty_tile, 1, 1, imagecolorallocate($empty_tile, 87, 0, 203));
            $source_image = &$this->tileset_image_mask;
        } else {
            $source_image = &$this->tileset_image;
        }

        //loop through the tiles
        foreach ($map as $tile_info) {
            //check if we're still in the CROP ZONE, if not skip or stop processing
            $x = ($i % $real_width) + $offset_x;
            $y = floor($i / $real_width) * 32 + $offset_y;
            $i += 32;
            if (is_array($box)) {
                if ($x + 32 < 0 || $y + 32 < 0 || $x - 32 > ($box[1][0] - $box[0][0])) {
                    continue;
                }
                if ($y - 32 > $box[1][1] - $box[0][1]) {
                    break;
                }
            }
            if ($tile_info['tile'] != 0 && !$tile_info['invisible']) {
                $tile_x = (($tile_info['tile'] * 32) % 320);
                $tile_y = (floor($tile_info['tile'] / 10) * 32);

                if ($tile_info['flipped'] || $tile_info['vflipped'] || ($tile_info['translucent'] && !$is_mask)) {
                    //an intermediate image is needed for flipping
                    $tile = imagecreatetruecolor(32, 32);
                    $transparent = imagecolorallocatealpha($tile, 87, 0, 203, 127);
                    imagefill($tile, 0, 0, $transparent);
                    imagecopy($tile, $source_image, 0, 0, $tile_x, $tile_y, 32, 32);

                    if ($tile_info['flipped'] && $tile_info['vflipped']) {
                        imageflip($tile, IMG_FLIP_BOTH);
                    } elseif ($tile_info['flipped']) {
                        imageflip($tile, IMG_FLIP_HORIZONTAL);
                    } elseif ($tile_info['vflipped']) {
                        imageflip($tile, IMG_FLIP_VERTICAL);
                    }

                    //render tile to layer image
                    if ($tile_info['translucent']) {
                        //imagecopymerge + alpha = disaster, so we have to use *another* intermediate image
                        $intermediate = imagecreatetruecolor(32, 32);
                        imagecopy($intermediate, $image, 0, 0, $x, $y, 32, 32);
                        imagecopy($intermediate, $tile, 0, 0, 0, 0, 32, 32);
                        imagecopymerge($image, $intermediate, $x, $y, 0, 0, 32, 32, 66);
                    } else {
                        imagecopy($image, $tile, $x, $y, 0, 0, 32, 32);
                    }

                    imagedestroy($tile);
                    unset($tile);

                } else {
                    imagecopy($image, $source_image, $x, $y, $tile_x, $tile_y, 32, 32);
                }
            } elseif ($is_mask) {
                imagecopy($image, $empty_tile, $x, $y, 0, 0, 32, 32);
            }
        }

        return $image;
    }


    /**
     * Render events
     *
     * @param resource $image Image to render events to
     *
     * @return  resource  Image with events rendered on top of it
     * @throws JJ2FileException  If tileset mask is not available
     */
    private function render_events($image) {
        if (!$this->has_tileset() || !isset($this->tileset_image_mask)) {
            throw new JJ2FileException('You need to load the tileset mask image before calling J2LFile::render_events()');
        }

        $settings = $this->get_settings();
        $event_mgr = new JJ2Events($this->settings['palette'], $this->resource_folder, $this->palette_remapping);

        if (!empty($this->mlle_settings) && isset($this->mlle_settings['weapons']) && count($this->mlle_settings['weapons']) >= 9) {
            for ($i = 7; $i <= 9; $i += 1) {
                if ($this->mlle_settings['weapons'][$i]['crate_event_ID']) {
                    $event_mgr->redirect($this->mlle_settings['weapons'][$i]['crate_event_ID'], $this->mlle_settings['weapons'][$i]['crate_meta_ID']);
                }
            }
        }

        foreach ($this->redirect as $from => $to) {
            $event_mgr->redirect($from, $to);
        }

        shuffle($this->player_names);
        $layer4 = &$this->layers[$settings['sprite_layer']];
        $tiles = $layer4['width'] * $layer4['height'];
        $tiles_x = $layer4['width'];
        $tiles_y = $layer4['height'];
        $offset_x = 0 - $this->box[0][0];
        $offset_y = 0 - $this->box[0][1];

        //events are stored per-tile
        $margin = 64;
        $bob_offsets = [0, -3, -6, -3];
        $events = new BufferReader($this->get_substream(2));
        for ($i = 0; $i < $tiles; $i += 1) {
            $tile_x = ($i % $tiles_x);
            $tile_y = floor($i / $tiles_x);

            // distinguish between 'real' position and effective position
            // real position is absolute pixel coordinates in the level; effective position is pixel coordinate in image
            // that we are rendering to. The distinction is necessary to properly apply gravity to events, because for
            // that we need to take into account the full level mask
            $real_pos_x = ($tile_x * 32);
            $real_pos_y = ($tile_y * 32);

            if ($real_pos_x < $this->box[0][0] - $margin || $real_pos_x > $this->box[1][0] + $margin || $real_pos_y < $this->box[0][1] - $margin) {
                continue;
            }

            if ($real_pos_y + $margin > $this->box[1][1]) {
                break;
            }

            $pos_x = $real_pos_x + $offset_x;
            $pos_y = $real_pos_y + $offset_y;

            $events->seek($i * 4);
            $event_bytes = $events->uint32();

            $event_ID = $event_bytes & 255; //event ID = first 8 bits
            if ($event_ID == 0) {
                continue;
            }

            //generators
            if ($event_ID == 216) {
                $event_params = $event_bytes >> 12; //skip the event ID bits and some other parameters
                $event_ID = JJ2Events::get_event_param($event_params, 0, 8);
                $event_bytes = $event_ID;
            }

            if (!$event_mgr->is_visible($event_ID)) {
                continue;
            }

            $on_ground = imagecolorat($this->level_mask, $real_pos_x, max(0, $real_pos_y + 48)) == 0;
            $event = $event_mgr->get_event($event_bytes, $on_ground);

            //only render Normal & MP Only events
            if ($event->difficulty != 0 && $event->difficulty != 3) {
                continue;
            }

            //do the pickup bob
            if ($event->is_pickup) {
                $pos_y += $bob_offsets[$tile_x % 4];
                if (($tile_x - $tile_y) % 2 == 0) { //flip if x and y are both odd/both even, for a checkerboard pattern
                    $event->flip_x = true;
                }
            }

            //handle horizontal springs, which dynamically flip etc
            if ($event_ID == 91 || $event_ID == 92 || $event_ID == 93) {
                $check_x = $pos_x + 16;
                $distance_left = $distance_right = 0;
                //see which side is the closest to a wall
                while (imagecolorat($this->level_mask, $check_x, $pos_y) != 0 && $distance_left < 32) {
                    $check_x -= 1;
                    $distance_left += 1;
                }
                $check_x += $distance_left;
                while (imagecolorat($this->level_mask, $check_x, $pos_y) != 0 && $distance_right < 32) {
                    $check_x += 1;
                    $distance_right += 1;
                }
                if ($distance_left > $distance_right) {
                    $event->flip_x = true;
                    $pos_x += $event->sprite[0]['width'] + 10;
                }
            }

            if ($event->flip_x) {
                imageflip($event->sprite[1], IMG_FLIP_HORIZONTAL);
            }

            if ($event->flip_y) {
                imageflip($event->sprite[1], IMG_FLIP_VERTICAL);
            }

            //draw player names for the heck of it
            if($event_ID == 31 || ($settings['is_multiplayer'] && in_array($event_ID, [29, 30, 32]))) {
                if(!isset($this->name_index)) {
                    $this->name_index = 0;
                }

                //don't repeat names multiple times, that would be unrealistic!!
                $suffix = '';
                if($this->name_index >= count($this->player_names)) {
                    $suffix = ceil($this->name_index / (count($this->player_names) - 1));
                }

                $name = $this->player_names[$this->name_index % (count($this->player_names) - 1)].$suffix;

                $label = new JJ2Text($name, $this->palette, $this->resource_folder);
                $label = $label->get_image(JJ2Text::SIZE_NORMAL);

                //create new sprite which combines the rabbit sprite and the name label
                $extra_height = 13 + 25 + $event->sprite[0]['hotspoty'];
                $extra_width = max($event->sprite[0]['width'], imagesx($label)) - $event->sprite[0]['width'];

                $event->sprite[0]['width'] += $extra_width;
                $event->sprite[0]['height'] += $extra_height;
                $event->sprite[0]['hotspoty'] += $extra_height;
                $event->sprite[0]['hotspotx'] += floor($extra_width / 2);

                $sprite_with_label = imagecreatetruecolor($event->sprite[0]['width'], $event->sprite[0]['height']);
                imagefill($sprite_with_label, 0, 0, imagecolorallocatealpha($sprite_with_label, 0, 0, 0, 127));
                imagealphablending($sprite_with_label, true);

                imagecopy($sprite_with_label, $event->sprite[1], floor($extra_width / 2), $extra_height, 0, 0, imagesx($event->sprite[1]), imagesy($event->sprite[1]));
                imagecopy($sprite_with_label, $label, 0, 0, 0, 0, imagesx($label), imagesy($label));

                //adjust offset so it is still drawn at the original position
                $event->sprite[1] = $sprite_with_label;
                $event->offset_y -= $extra_height;

                $this->name_index += 1;
            }

            //simulate gravity if it applies, basically just increase y position until a mask is met
            if ($event->feels_gravity) {
                $test_x = min($tiles_x * 32, max($real_pos_x - $event->sprite[0]['coldspotx'], 0));
                while ($real_pos_y < imagesy($this->level_mask) && imagecolorat($this->level_mask, $test_x, $real_pos_y) != 0) {
                    $real_pos_y += 1;
                }
                $compare = $event->use_hotspot ? $event->sprite[0]['hotspoty'] : $event->sprite[0]['coldspoty'];
                if ($compare != 0) {
                    $real_pos_y += $compare;
                } else {
                    $real_pos_y -= $event->sprite[0]['height'];
                }
                $pos_y = $real_pos_y + $offset_y;
            } elseif ($event->is_pickup || $event->always_adjust) {
                //floating stuff is centered, apparently
                $pos_x += 16 + $event->sprite[0]['hotspotx'];
                $pos_y += 16 + $event->sprite[0]['hotspoty'];
            }

            $pos_x += $event->offset_x;
            $pos_y += $event->offset_y;

            if ($event->opacity != 100) {
                imagecopymerge_alpha($image, $event->sprite[1], $pos_x, $pos_y, 0, 0, $event->sprite[0]['width'], $event->sprite[0]['height'], $event->opacity);
            } else {
                imagecopy($image, $event->sprite[1], $pos_x, $pos_y, 0, 0, $event->sprite[0]['width'], $event->sprite[0]['height']);
            }
        }

        return $image;
    }


    /**
     * Render game HUD on top of image
     *
     * Renders the vanilla JJ2 HUD over the image, as suitable for the given game and character. Elements will be drawn
     * in the correct corners, regardless of input image size.
     *
     * @param resource $image  Image to render HUD on
     * @param string $mode  Game mode, one of 'battle', 'ctf', 'treasure', 'race', 'singleplayer'
     * @param string $rabbit  Character, one of 'jazz', 'spaz', 'lori'
     *
     * @return resource GD Image resource for preview image
     */
    public function render_hud($image, $mode = 'singleplayer', $rabbit = 'jazz') {
        $j2a = new J2AFile($this->resource_folder.DIRECTORY_SEPARATOR.'Anims.j2a', $this->palette, $this->resource_folder);

        // left side elements
        if($mode == 'battle') {
            // just the score
            $text = (new JJ2Text("roasts 0 /10", $this->palette, $this->resource_folder))->get_image(JJ2Text::SIZE_MEDIUM);
            imagecopy($image, $text, 6, 2, 0, 0, imagesx($text), imagesy($text));

        } elseif($mode == 'ctf') {
            // scores for both teams - actually simpler in JJ2+ (though JJ2Text doesn't support colours)
            $blue = (new JJ2Text("Blue 0/10", $this->palette, $this->resource_folder))->get_image(JJ2Text::SIZE_MEDIUM);
            $red = (new JJ2Text("Red 0/10", $this->palette, $this->resource_folder))->get_image(JJ2Text::SIZE_MEDIUM);
            imagecopy($image, $blue, 6, 2, 0, 0, imagesx($blue), imagesy($blue));
            imagecopy($image, $red, 6, 2 + imagesy($blue) + 14, 0, 0, imagesx($red), imagesy($red));

            $flag_blue = $j2a->get_frame(44, 4, 1)[1];
            $flag_red = $j2a->get_frame(44, 8, 1)[1];
            imagecopy($image, $flag_blue, 6 + imagesx($blue) + 8, 8, 0, 0, imagesx($flag_blue), imagesy($flag_blue));
            imagecopy($image, $flag_red, 6 + imagesx($blue) + 8, 8 + imagesy($blue) + 14, 0, 0, imagesx($flag_red), imagesy($flag_red));

        } elseif($mode == 'treasure') {
            // a gem (yay, LUTs) and the score
            $lut = [55,55,54,53,52,51,50,49,48,15,48,15,15,48,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,0,0,0];
            $gem = $j2a->get_frame(71, 22, 4, $this->palette, $lut)[1];
            imagecopy($image, $gem, -3, -3, 0, 0, imagesx($gem), imagesy($gem));
            $score = (new JJ2Text("0\\10", $this->palette, $this->resource_folder))->get_image(JJ2Text::SIZE_NORMAL);
            imagecopy($image, $score, 22, 2, 0, 0, imagesx($score), imagesy($score));

        } elseif($mode == 'race') {
            // the lap, and current time
            $lap = (new JJ2Text("Lap 1\\5", $this->palette, $this->resource_folder))->get_image(JJ2Text::SIZE_MEDIUM);
            $time = (new JJ2Text("0:04:56", $this->palette, $this->resource_folder))->get_image(JJ2Text::SIZE_MEDIUM);
            imagecopy($image, $lap, 8, 2, 0, 0, imagesx($lap), imagesy($lap));
            imagecopy($image, $time, 14, -3 + imagesy($lap), 0, 0, imagesx($time), imagesy($time));

        } elseif($mode == 'singleplayer') {
            // score, and character head + amount of lives
            $score = (new JJ2Text("00000000", $this->palette, $this->resource_folder))->get_image(JJ2Text::SIZE_MEDIUM);
            imagecopy($image, $score, 8, 2, 0, 0, imagesx($score), imagesy($score));

            $lives = (new JJ2Text("x3", $this->palette, $this->resource_folder))->get_image(JJ2Text::SIZE_MEDIUM);
            imagecopy($image, $lives, 34, imagesy($image) - imagesy($lives) - 3, 0, 0, imagesx($lives), imagesy($lives));

            $anim = ['jazz' => 3, 'lori' => 4, 'spaz' => 5][$rabbit];
            $head = $j2a->get_frame(39, $anim, 0)[1];
            imagecopy($image, $head, 3, imagesy($image) - imagesy($head) - 1, 0, 0, imagesx($head), imagesy($head));
        }

        // health: 3 or 5 hearts, if appropriate for game mode
        if(in_array($mode, ['battle', 'ctf', 'singleplayer'])) {
            $health = ($mode == 'ctf') ? 3 : 5;
            $heart = $j2a->get_frame(71, 41, 0)[1];
            $y = 3;
            $x = imagesx($image) - 7 - (5 * (imagesx($heart) + 1));
            for($i = 0; $i < $health; $i += 1) {
                imagecopy($image, $heart, $x, $y, 0, 0, imagesx($heart), imagesy($heart));
                $x += imagesx($heart) + 1;
            }
        }

        // ammo
        // does not take custom weapons into account (todo?)
        $gun_offset = 5;
        if(in_array($rabbit, ['jazz', 'spaz'])) {
            $gun = $j2a->get_frame(71, ($rabbit == 'spaz' ? 30 : 29), 1)[1];
            if($rabbit == 'spaz') {
                $gun_offset = 3;
            }
        } else {
            // lori has no gun sprite in TSF - get it from plus.j2a instead
            $plusj2a = new J2AFile($this->resource_folder.DIRECTORY_SEPARATOR.'plus.j2a', $this->palette, $this->resource_folder);
            $gun = $plusj2a->get_frame(0, 5, 0)[1];
        }

        // ^ = infinity sign in jj2 font
        $ammo = (new JJ2Text("x^", $this->palette, $this->resource_folder))->get_image(JJ2Text::SIZE_MEDIUM);
        imagecopy($image, $gun, imagesx($image) - 80 - imagesx($gun), imagesy($image) - $gun_offset - imagesy($gun), 0, 0, imagesx($gun), imagesy($gun));
        imagecopy($image, $ammo, imagesx($image) - 77, imagesy($image) - 2 - imagesy($ammo), 0, 0, imagesx($ammo), imagesy($ammo));

        return $image;
    }


    /**
     * Generate preview
     *
     * Wrapper for get_image() that tries to find some sensible parameters for it and calls it. If level is larger than
     * max allowed preview image dimensions, uses the max allowed size as boundaries and centres it on a start position.
     *
     * @return resource GD Image resource for preview image
     */
    public function get_preview($is_screenshot = false) {
        $box = $this->get_visible_box();
        $width = $box[1][0] - $box[0][0];
        $height = $box[1][1] - $box[0][1];

        $start_pos = $this->get_start_pos();
        $rabbit = $start_pos[1];
        $start_pos = $start_pos[0];

        //if level is larger than max view area, center on *a* start position
        if ($width * $height > $this->budget) {
            $ratio = $height / $width;

            $width_optimal = floor(sqrt($this->budget / $ratio));
            $half_width = $width_optimal / 2;
            if ($start_pos[0] + $half_width > $width) {
                $box[0][0] = $width - $width_optimal;
                $box[1][0] = $width;
            } elseif ($start_pos[0] - $half_width < 0) {
                $box[0][0] = 0;
                $box[1][0] = $width_optimal;
            } else {
                $box[0][0] = $start_pos[0] - $half_width;
                $box[1][0] = $start_pos[0] + $half_width;
            }

            $height_optimal = floor($width_optimal * $ratio);
            $half_height = $height_optimal / 2;
            if ($start_pos[1] + $half_height > $height) {
                $box[0][1] = $height - $height_optimal;
                $box[1][1] = $height;
            } elseif ($start_pos[1] - $half_height < 0) {
                $box[0][1] = 0;
                $box[1][1] = $height_optimal;
            } else {
                $box[0][1] = $start_pos[1] - $half_height;
                $box[1][1] = $start_pos[1] + $half_height;
            }
        }

        $preview = $this->get_image([], true, $box);

        if($is_screenshot) {
            $crop_x = max(0, $start_pos[0] - $box[0][0] - 400);
            $crop_y = max(0, $start_pos[1] - $box[0][1] - 300);

            $screenshot = imagecreatetruecolor(800, 600);
            imagecopy($screenshot, $preview, 0, 0, $crop_x, $crop_y, 800, 600);

            return $this->render_hud($screenshot, 'singleplayer', $rabbit);
        } else {
            return $preview;
        }
    }
}
