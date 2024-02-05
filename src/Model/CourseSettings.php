<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\Plugin\MatrixChatClient\Model;

class CourseSettings
{
    private int $courseId;
    private ?MatrixRoom $matrixRoom = null;
    private ?string $matrixRoomId = null;

    public function __construct(int $courseId, ?string $matrixRoomId = null)
    {
        $this->courseId = $courseId;
        $this->matrixRoomId = $matrixRoomId;
    }

    public function getCourseId(): int
    {
        return $this->courseId;
    }

    public function getMatrixRoomId(): ?string
    {
        return $this->matrixRoomId;
    }

    public function setMatrixRoomId(?string $matrixRoomId): CourseSettings
    {
        $this->matrixRoomId = $matrixRoomId;
        return $this;
    }
}
