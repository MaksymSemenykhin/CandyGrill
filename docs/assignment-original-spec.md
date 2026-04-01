# Original assignment specification (employer TZ)

Verbatim requirements as provided for the CandyGrill / game server task.

---

## Objective

Your objective is to implement a server for a simple online game using PHP and mysql. It should take you 6-10 hours to get the job done, however you are free to use more time to reasonably improve your solution. We will look at the code structure, object-oriented design, database skills and performance.

## Requirements

1. The use of PHP frameworks is discouraged;
2. Server should be able to handle multiple requests simultaneously;
3. Server should scale well (millions of users expected). Non-relational solutions (etc memcached) can be used;
4. Server should be easily extensible with new APIs.

## Details

### Communication

Game client will be sending POST requests to the server. The body of each request will contain a command. The server will handle a command and will respond with a command of its own which will be delivered in a response body. Commands will be transferred in JSON format, specific JSON syntax is up to you.

### Overview

Each player has a single character. This character has certain attributes and can fight other players’ characters to increase its attributes.

### Characters

For each character the following attributes should be stored:

1. Name
2. Level
3. The number of fights
4. The number of fights won
5. The number of coins
6. The value of skill #1
7. The value of skill #2
8. The value of skill #3

The set of character’s attributes should be easily extendable. Skill values are chosen randomly from the 0-50 range when the character is created.

### Combat

Combat is a fight between two players’ characters.

Combat is a sequence of attacks. To attack the opponent, a player chooses a skill. If his character’s value of this skill is not higher than the opponent’s, he scores 0 points. Otherwise, he scores the amount of points equal to the difference of skill values.

Players take turns attacking each other. Combat is waged in 3 rounds, in each round the players attack each other once. The right to attack first is assigned randomly at the start of the combat.

A player can use any skill to attack, however:

- he can’t use the same skill 2 times in a row;
- he can’t use the skill that was just used by his opponent.

Combat is won by the player who scores more points after 3 rounds. In case of a draw, the winner is chosen randomly. If at any time in a combat one of the players has more than 100 points, he wins the combat immediately. The winner receives a certain number of coins.

The player who initiates the combat, makes his moves online. The server automatically make moves for his opponent.

### Levelling up

The character’s level is increased after a certain number of wins. In the future a player will also be able to increase the values of his character’s skills by spending a certain amount of coins.

### Requests

The server should support the following requests:

1. **Registration.** The client sends the **name** for his character, the server creates the character and responds with a **player identifier**;
2. **Login.** The client sends a **player identifier**, the server logs him in and returns a **session identifier** which should be used for all subsequent client requests;
3. **Choosing an opponent.** The client sends a request to choose an opponent, the server randomly chooses two possible opponents who have the **same level** as the client and returns the **ids and names** of these opponents;
4. **Starting a combat.** The client sends an **id of the opponent** it wants to fight. The server response contains the **chosen opponent’s skill values** and, if the opponent was chosen to act first, **the opponent’s first move**;
5. **Attacking.** The client sends an **id of the skill** to attack with. The server responds with **move results**, **the opponent’s next move**.
6. **Claiming the prize.** The client sends a request to claim the prize. The server **applies the results of the combat** and responds with the **changes in character’s attributes**.

If the combat is over after applying the **4th or 5th** request, the response should signal that fact and contain the **number of coins won**. The results of the combat are **only applied after the 6th request**.

### Solution

You are free to use any environment to develop and run the application. The solution should be delivered in an archive. The archive should include all source code (including tests, if there are any), a **database dump** and a **readme.txt** file with your notes, thoughts on the task and anything else you think we should know.
