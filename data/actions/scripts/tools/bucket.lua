local drunk = Condition(CONDITION_DRUNK)
drunk:setParameter(CONDITION_PARAM_TICKS, 60000)

local haste = Condition(CONDITION_HASTE)
haste:setParameter(CONDITION_PARAM_TICKS, 60000)

local poison = Condition(CONDITION_POISON)
poison:setParameter(CONDITION_PARAM_DELAYED, true)
poison:setParameter(CONDITION_PARAM_MINVALUE, -50)
poison:setParameter(CONDITION_PARAM_MAXVALUE, -120)
poison:setParameter(CONDITION_PARAM_STARTVALUE, -5)
poison:setParameter(CONDITION_PARAM_TICKINTERVAL, 4000)
poison:setParameter(CONDITION_PARAM_FORCEUPDATE, true)

local fluidType = {3, 4, 5, 7, 10, 11, 13, 15, 19, 20}
local fluidMessage = {"Aah...", "Urgh!", "Mmmh.", "Aaaah...", "Aaaah...", "Urgh!", "Urgh!", "Aah...", "Urgh!", "Ahhhh"}

function onUse(player, item, fromPosition, target, toPosition, isHotkey)
local chance = math.random (1,10)
	if target.itemid == 28732 and item.type == 1 then
		item:transform(item.itemid, item.type - 1)
		if chance <= 5 then
			target:transform(28733)
			player:sendTextMessage(MESSAGE_INFO_DESCR, "Your bath tub is full!")
		return true
	end
	elseif target.itemid == 28733 and item.type == 0 then
		item:transform(item.itemid, 1)
		if chance <= 5 then
			target:transform(28732)
			player:sendTextMessage(MESSAGE_INFO_DESCR, "Your bath tub is empty!")
		end
		return true
	end
		
    local targetItemType = ItemType(target.itemid)
    if targetItemType and targetItemType:isFluidContainer() then
        if target.type == 0 and item.type ~= 0 then
            target:transform(target:getId(), item.type)
            item:transform(item:getId(), 0)
            return true
        elseif target.type ~= 0 and item.type == 0 then
            target:transform(target:getId(), 0)
            item:transform(item:getId(), target.type)
            return true
        end
    end

    if target:isCreature() then
        if item.type == 0 then
            player:sendTextMessage(MESSAGE_STATUS_SMALL, "It is empty.")
        elseif target == player then
            if item.type == 3 or item.type == 15 then
                player:addCondition(drunk)
            elseif item.type == 4 then
                player:addCondition(poison)
            elseif item.type == 20 then
                player:addCondition(haste)
            elseif item.type == 7 then
                player:addMana(math.random(50, 150))
                fromPosition:sendMagicEffect(CONST_ME_MAGIC_BLUE)
            elseif item.type == 10 then
                player:addHealth(60)
                fromPosition:sendMagicEffect(CONST_ME_MAGIC_BLUE)
            end
            item:transform(item:getId(), 0)
            for i = 0, #fluidType do
                if item.type == fluidType[i] or item.type == 20 then
                    player:say(fluidMessage[i], TALKTYPE_MONSTER_SAY)
                    return true
                end
            end
            player:say("Gulp.", TALKTYPE_MONSTER_SAY)
        else
            Game.createItem(2016, item.type, toPosition):decay()
            item:transform(item:getId(), 0)
        end
    else
        local fluidSource = targetItemType and targetItemType:getFluidSource() or 0
        if fluidSource ~= 0 then
            item:transform(item:getId(), fluidSource)
        elseif item.type == 0 then
            player:sendTextMessage(MESSAGE_STATUS_SMALL, "It is empty.")
        else
            if toPosition.x == CONTAINER_POSITION then
                toPosition = player:getPosition()
            end
            Game.createItem(2016, item.type, toPosition):decay()
            item:transform(item:getId(), 0)
        end
    end
	return true
end
