<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Upload\Enums;

/**
 * Место назначения загрузки фотографии.
 *
 * Используется внутри PendingPhotoUpload для диспатча
 * на нужную тройку методов VK API (getServer / upload / save).
 *
 * @internal
 */
enum PhotoDestination
{
    case Messages;
    case Wall;
    case Album;
    case OwnerPhoto;
    case ChatPhoto;
    case Cover;
    case Market;
    case MarketAlbum;
    case Poll;
}
