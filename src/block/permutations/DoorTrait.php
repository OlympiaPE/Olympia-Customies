<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\permutations;

use Exception;
use Olympia\Kitmap\blocks\types\fencegate\FenceGateOpen;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\data\bedrock\block\convert\BlockStateReader;
use pocketmine\data\bedrock\block\convert\BlockStateWriter;
use pocketmine\math\Facing;
use pocketmine\nbt\tag\CompoundTag;

trait DoorTrait
{
    use HorizontalFacingTrait;

    private bool $open = false;

	/**
	 * @return BlockProperty[]
	 */
	public function getBlockProperties(): array {
		return [
			new BlockProperty("customies:door", [0, 1]),
            new BlockProperty("customies:rotation", [2, 3, 4, 5]),
		];
	}

	/**
	 * @return Permutation[]
	 */
	public function getPermutations(): array {
		return [
			(new Permutation("q.block_property('customies:door') == 0"))
				->withComponent("minecraft:geometry", CompoundTag::create()
                    ->setString("identifier", static::GEOMETRY_CLOSE)),
			(new Permutation("q.block_property('customies:door') == 1"))
                ->withComponent("minecraft:geometry", CompoundTag::create()
                    ->setString("identifier", static::GEOMETRY_OPEN)),
            (new Permutation("q.block_property('customies:rotation') == 2"))
                ->withComponent("minecraft:transformation", CompoundTag::create()
                    ->setInt("RX", 0)
                    ->setInt("RY", 0)
                    ->setInt("RZ", 0)
                    ->setFloat("SX", 1.0)
                    ->setFloat("SY", 1.0)
                    ->setFloat("SZ", 1.0)
                    ->setFloat("TX", 0.0)
                    ->setFloat("TY", 0.0)
                    ->setFloat("TZ", 0.0)),
            (new Permutation("q.block_property('customies:rotation') == 3"))
                ->withComponent("minecraft:transformation", CompoundTag::create()
                    ->setInt("RX", 0)
                    ->setInt("RY", 2)
                    ->setInt("RZ", 0)
                    ->setFloat("SX", 1.0)
                    ->setFloat("SY", 1.0)
                    ->setFloat("SZ", 1.0)
                    ->setFloat("TX", 0.0)
                    ->setFloat("TY", 0.0)
                    ->setFloat("TZ", 0.0)),
            (new Permutation("q.block_property('customies:rotation') == 4"))
                ->withComponent("minecraft:transformation", CompoundTag::create()
                    ->setInt("RX", 0)
                    ->setInt("RY", 1)
                    ->setInt("RZ", 0)
                    ->setFloat("SX", 1.0)
                    ->setFloat("SY", 1.0)
                    ->setFloat("SZ", 1.0)
                    ->setFloat("TX", 0.0)
                    ->setFloat("TY", 0.0)
                    ->setFloat("TZ", 0.0)),
            (new Permutation("q.block_property('customies:rotation') == 5"))
                ->withComponent("minecraft:transformation", CompoundTag::create()
                    ->setInt("RX", 0)
                    ->setInt("RY", 3)
                    ->setInt("RZ", 0)
                    ->setFloat("SX", 1.0)
                    ->setFloat("SY", 1.0)
                    ->setFloat("SZ", 1.0)
                    ->setFloat("TX", 0.0)
                    ->setFloat("TY", 0.0)
                    ->setFloat("TZ", 0.0)),
		];
	}

	public function getCurrentBlockProperties(): array
    {
		return [$this->open, $this->facing];
	}

    /**
     * @throws Exception
     */
    protected function writeStateToMeta(): int
    {
		return Permutations::toMeta($this);
	}

    /**
     * @throws Exception
     */
    public function readStateFromData(int $id, int $stateMeta): void
    {
		$blockProperties = Permutations::fromMeta($this, $stateMeta);
		$this->open = $blockProperties[0] ?? false;
        $this->facing = $blockProperties[1] ?? Facing::NORTH;
	}

	public function getStateBitmask(): int
    {
		return Permutations::getStateBitmask($this);
	}

	public function serializeState(BlockStateWriter $out): void
    {
		$out->writeBool("customies:door", $this->open);
        $out->writeInt("customies:rotation", $this->facing);
	}

	public function deserializeState(BlockStateReader $in): void
    {
		$this->open = $in->readBool("customies:door");
        $this->facing = $in->readInt("customies:rotation");
	}


    public function isOpen(): bool
    {
        return $this->open;
    }

    /**
     * @param bool $open
     * @return \Olympia\Kitmap\blocks\types\fencegate\FenceGateOpen|DoorTrait
     */
    public function setOpen(bool $open): self
    {
        $this->open = $open;
        return $this;
    }
}