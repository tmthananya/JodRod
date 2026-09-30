# Project Brief: Flood Parking Community (JodRod)

> **Source of Truth** for the Flood Parking Community Project.

---

## 1. Project Overview
* **Project Name:** Flood Parking Community (JodRod)
* **Core Purpose:** Finding and offering safe parking spaces during flood emergencies.
* **Guiding Principle:** Real-world civic utility. The system must feel realistic and practical, not like a fantasy game. Game mechanics (points) are secondary and directly reward real community actions.

---

## 2. Problem Statement
During severe flooding, car owners urgently need to evacuate their vehicles to higher ground or safe facilities to prevent catastrophic flood damage. Concurrently, local property owners, temples, businesses, or residents may have unused, safe elevated parking spaces but lack a direct, rapid mechanism to make them known to their community.

---

## 3. Solution
A focused, real-world web application where:
1. People with cars can discover safe, available parking spaces on a real map.
2. People with available private/community parking space can list and offer it to others.
3. Parking spaces are displayed on a real-world interactive map (Leaflet / OpenStreetMap).
4. Users can perform complete CRUD operations (Create, Read, Update, Delete) on parking spaces.
5. Users can create and manage parking requests.
6. A parking transaction is verified and completed through step-by-step **mutual confirmation**.
7. Community points are awarded upon verified completion of parking assistance.

---

## 4. User Roles
1. **Driver:** A vehicle owner needing a safe parking space during flooding.
2. **Parking Owner:** A community member or property owner offering an elevated safe space.

> **Note on Authentication:** A single user may participate in either role. A complex login or role-gated authentication system is intentionally **not** being built at this stage.

---

## 5. Core Data Specification

### Phase 1 Entity: `parking_spaces`
The primary database entity for the initial phase contains strictly the essential fields required for complete parking CRUD:

| Field | Type | Description |
| :--- | :--- | :--- |
| `id` | UUID / String | Unique identifier for the parking space |
| `name` | String | Name or landmark description of the parking space |
| `latitude` | Float / Numeric | Exact latitude coordinate on the real map |
| `longitude` | Float / Numeric | Exact longitude coordinate on the real map |
| `capacity` | Integer | Total vehicle capacity of the space |
| `available_spaces` | Integer | Currently available vehicle slots |
| `status` | String | Current status of the parking space (`available`, `full`, `closed`) |

### Parking Space Statuses:
* `available`: Space is open and has available slots.
* `full`: Space has reached maximum capacity (`available_spaces == 0`).
* `closed`: Space is temporarily or permanently closed by the owner.

---

## 6. Core CRUD Scope (Phase 1)
* **Create:** Register a new parking space by providing name, capacity, and dropping/selecting a pin on the real map.
* **Read:** Display all active parking spaces on the real map and listing view with live available capacity.
* **Update:** Allow the parking owner to modify capacity, adjust available slots, or update the status (`available`, `full`, `closed`).
* **Delete:** Remove a parking space listing from the active database/store.

---

## 7. Next Phase: Parking Requests & Mutual Confirmation (Phase 2)
*To be implemented ONLY after `parking_spaces` CRUD is fully functional and verified.*

### Desired Final Flow:
1. **Index**
2. **Find Parking**
3. **View parking spaces on real map**
4. **Request parking**
5. **Parking owner accepts**
6. **Driver confirms arrival**
7. **Parking owner confirms parking**
8. **Completed**
9. **Points awarded**

---

## 8. Explicit Non-Goals (What We Are NOT Building)
To maintain strict focus on the real-world flood parking emergency, the following are explicitly out of scope:
* No complex login or authentication systems
* No real-time chat or messaging system
* No payment gateways or fee transactions
* No automatic background GPS tracking
* No external weather API integrations
* No algorithmic flood prediction simulations
* No AI-driven automated rescue dispatchers
* No social media feeds or social sharing
* No administrative overhead dashboards
* No unnecessary analytics or telemetry tracking
* No fake permanent parking datasets (system starts from zero/real community input)
