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

namespace ILIAS\Plugin\MatrixChatClient\Job;

use ilCronJob;
use ilCronJobResult;
use ILIAS\DI\Container;
use ILIAS\Plugin\MatrixChatClient\Controller\ChatController;
use ILIAS\Plugin\MatrixChatClient\Model\UserConfig;
use ILIAS\Plugin\MatrixChatClient\Model\UserRoomAddQueue;
use ILIAS\Plugin\MatrixChatClient\Repository\CourseSettingsRepository;
use ILIAS\Plugin\MatrixChatClient\Repository\QueuedInvitesRepository;
use ilLogger;
use ilMatrixChatClientPlugin;
use ilObject;
use ilObjUser;
use ReflectionClass;
use Throwable;

class ProcessQueuedInvitesJob extends ilCronJob
{
    private Container $dic;
    private ilMatrixChatClientPlugin $plugin;
    private ilLogger $logger;
    private QueuedInvitesRepository $queuedInvitesRepo;
    private CourseSettingsRepository $courseSettingsRepo;

    public function __construct(Container $dic, ilMatrixChatClientPlugin $plugin)
    {
        $this->dic = $dic;
        $this->plugin = $plugin;
        $this->logger = $this->dic->logger()->root();
        $this->queuedInvitesRepo = QueuedInvitesRepository::getInstance($this->dic->database());
        $this->courseSettingsRepo = CourseSettingsRepository::getInstance();
    }

    public function getId(): string
    {
        return (new ReflectionClass($this))->getShortName();
    }

    public function getTitle(): string
    {
        return $this->plugin->txt("job.title");
    }

    public function getDescription(): string
    {
        return $this->plugin->txt("job.description");
    }

    public function hasAutoActivation(): bool
    {
        return true;
    }

    public function hasFlexibleSchedule(): bool
    {
        return true;
    }

    public function getDefaultScheduleType(): int
    {
        return self::SCHEDULE_TYPE_IN_HOURS;
    }

    public function getDefaultScheduleValue(): ?int
    {
        return 1;
    }

    public function run(): ilCronJobResult
    {
        $cronResult = new ilCronJobResult();

        $matrixApi = $this->plugin->getMatrixApi();

        $total = 0;
        $skipped = 0;
        $invited = 0;
        $failed = 0;

        if (!$this->plugin->getPluginConfig()->getMatrixSpaceId()) {
            $this->logger->error("Unable to continue processing queued invitations. Space not configured");
            $cronResult->setMessage($this->plugin->txt("config.space.status.disconnected"));
            $cronResult->setStatus(ilCronJobResult::STATUS_FAIL);
            return $cronResult;
        }
        $space = $matrixApi->getSpace($this->plugin->getPluginConfig()->getMatrixSpaceId());
        if (!$space) {
            $this->logger->error("Unable to continue processing queued invitations. Space configured but not found");
            $cronResult->setMessage($this->plugin->txt("config.space.status.faulty"));
            $cronResult->setStatus(ilCronJobResult::STATUS_FAIL);
            return $cronResult;
        }

        /** @var UserRoomAddQueue[] $queuedInvites */
        foreach ($this->queuedInvitesRepo->readAllGroupedByRefId() as $refId => $queuedInvites) {
            $total += count($queuedInvites);

            if (!ilObject::_exists($refId, true)) {
                $this->logger->warning("Skipping invite queue check for object with ref-id '$refId'. Object does not exist");
                $skipped += count($queuedInvites);
                continue;
            }
            $courseSettings = $this->courseSettingsRepo->read($refId);
            if (!$courseSettings->getMatrixRoomId()) {
                $this->logger->info("Skipping invite queue check for object with ref-id '$refId'. Matrix-Room-ID not defined");
                $skipped += count($queuedInvites);
                continue;
            }

            $room = $matrixApi->getRoom($courseSettings->getMatrixRoomId());
            if (!$room) {
                $this->logger->warning("Unable to process invite queue for object with ref_id '$refId'. Matrix-Room-ID defined but room not found.");
                $skipped += count($queuedInvites);
                continue;
            }

            foreach ($queuedInvites as $queuedInvite) {
                try {
                    $user = new ilObjUser($queuedInvite->getUserId());
                } catch (Throwable $ex) {
                    $this->logger->warning(sprintf(
                        "Unable to process invite of user with id '%s' to course with ref-id '%s'",
                        $queuedInvite->getUserId(),
                        $refId
                    ));
                    if (!$this->queuedInvitesRepo->delete($queuedInvite)) {
                        $this->logger->warning(sprintf(
                            "Error occurred while trying to remove queued invite of user with id '%s' to course with ref-id '%s'",
                            $queuedInvite->getUserId(),
                            $refId
                        ));
                    }
                    $skipped++;
                    continue;
                }

                $userConfig = (new UserConfig($user))->load();

                if (!$userConfig->getMatrixUserId()) {
                    $this->logger->info(sprintf(
                        "Can't continue processing queued invite of user with id '%s' to course with ref-id '%s'. Matrix-User-ID not defined yet.",
                        $user->getId(),
                        $refId
                    ));
                    $skipped++;
                    continue;
                }

                $matrixUser = $matrixApi->getUser($userConfig->getMatrixUserId());

                if (!$matrixUser) {
                    $this->logger->info(sprintf(
                        "Can't continue processing queued invite of user with id '%s' to course with ref-id '%s'. Can't retrieve Matrix User.",
                        $user->getId(),
                        $refId
                    ));
                    $skipped++;
                    continue;
                }

                if ($room->isMember($matrixUser)) {
                    $this->logger->info(sprintf(
                        "Matrix User '%s' is already a member of the room '%s' created for object with ref-id '%s'. Deleting invite from queue",
                        $matrixUser->getId(),
                        $room->getId(),
                        $refId
                    ));

                    if (!$this->queuedInvitesRepo->delete($queuedInvite)) {
                        $this->logger->warning(sprintf(
                            "Error occurred while trying to remove queued invite of user with id '%s' to course with ref-id '%s'",
                            $queuedInvite->getUserId(),
                            $refId
                        ));
                    }
                    $skipped++;
                    continue;
                }

                if (!$space->isMember($matrixUser)) {
                    if ($matrixApi->getStatusOfUserInRoom(
                        $space,
                        $matrixUser->getId()
                    ) === ChatController::USER_STATUS_INVITE) {
                        $this->logger->info(sprintf(
                            "Skipping inviting user '%s' to space '%s'. User already invited",
                            $matrixUser->getId(),
                            $space->getId()
                        ));
                        $skipped++;
                        continue;
                    }

                    if ($matrixApi->inviteUserToRoom($matrixUser, $space)) {
                        $this->logger->info(sprintf(
                            "Invited user '%s' to space '%s'",
                            $matrixUser->getId(),
                            $room->getId()
                        ));
                    }
                }

                if ($matrixApi->getStatusOfUserInRoom(
                    $room,
                    $matrixUser->getId()
                ) === ChatController::USER_STATUS_INVITE) {
                    $this->logger->info(sprintf(
                        "Skipping inviting user '%s' to room '%s'. User already invited",
                        $matrixUser->getId(),
                        $room->getId()
                    ));
                    $skipped++;
                    continue;
                }

                if ($matrixApi->inviteUserToRoom($matrixUser, $room)) {
                    $this->logger->info(sprintf(
                        "Invited user '%s' to room '%s' created for object with ref-id '%s'",
                        $matrixUser->getId(),
                        $room->getId(),
                        $refId
                    ));
                    $invited++;
                } else {
                    $this->logger->info(sprintf(
                        "Error occurred while trying to invite user '%s' to room '%s' created for object with ref-id '%s'",
                        $matrixUser->getId(),
                        $room->getId(),
                        $refId
                    ));
                    $failed++;
                }
            }
        }

        $cronResult->setStatus(ilCronJobResult::STATUS_OK);
        $cronResult->setMessage(sprintf(
            $this->plugin->txt("job.result"),
            $total,
            $skipped,
            $invited,
            $failed
        ));
        return $cronResult;
    }
}
