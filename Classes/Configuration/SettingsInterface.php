<?php

declare(strict_types=1);

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2025 Dirk Wenzel <wenzel@cps-it.de>
 *  All rights reserved
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the text file GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

namespace Cpsit\T3hauler\Configuration;

/**
 * Interface SettingsInterface
 */
interface SettingsInterface
{
    public const string NAME = 'T3hauler';
    public const string KEY = 't3hauler';
    public const string VENDOR_NAME = 'Cpsit';

    // Configuration keys
    public const string DETECTION_EXCLUDE_FIELDS = 'detection.excludeFields';
    public const string DETECTION_ENABLED_TABLES = 'detection.enabledTables';
    public const string EXPORT_FORMAT = 'export.format';
    public const string EXPORT_CHARSET = 'export.charset';
}
