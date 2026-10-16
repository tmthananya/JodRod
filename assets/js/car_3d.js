/**
 * JodRod 3D Car Engine
 * Stylized low-poly 3D flood rescue vehicle customizer using Three.js.
 * Built to match the architecture, interaction, and aesthetics of Character3D.
 */
(function() {
  class Car3D {
    constructor(canvas, options = {}) {
      this.canvas = canvas;
      this.options = Object.assign({
        bodyStyle: 'pickup',   // 'pickup', 'suv', 'van', 'compact'
        paintColor: '#ea580c',  // Hi-Vis Orange default
        wheelStyle: 'flood',   // 'flood', 'offroad', 'steelie', 'sport'
        detail: 'snorkel',     // 'snorkel', 'roofrack', 'beacon', 'standard'
        interactive: true,
        autoRotate: true
      }, options);

      this.scene = null;
      this.camera = null;
      this.renderer = null;
      this.carGroup = null;
      this.bodyMeshGroup = null;
      this.wheelsGroup = null;
      this.detailGroup = null;
      this.materials = {};
      this.animationFrameId = null;
      this.clock = new THREE.Clock();
      this.isDragging = false;
      this.prevMouseX = 0;
      this.rotationVelocity = 0;

      this.init();
    }

    init() {
      if (!window.THREE) {
        console.error('Three.js is not loaded');
        return;
      }

      const rect = this.canvas.getBoundingClientRect();
      const width = rect.width || this.canvas.width || 340;
      const height = rect.height || this.canvas.height || 300;

      this.renderer = new THREE.WebGLRenderer({
        canvas: this.canvas,
        antialias: true,
        preserveDrawingBuffer: true,
        alpha: true
      });
      this.renderer.setSize(width, height, false);
      this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

      this.scene = new THREE.Scene();

      // Camera positioned for an isometric/3/4 vehicle studio perspective
      this.camera = new THREE.PerspectiveCamera(34, width / height, 0.1, 100);
      this.camera.position.set(3.4, 2.2, 4.2);
      this.camera.lookAt(0, 0.45, 0);

      // Studio Lighting
      const ambientLight = new THREE.AmbientLight(0xffffff, 0.82);
      this.scene.add(ambientLight);

      const mainLight = new THREE.DirectionalLight(0xfff7ed, 0.9);
      mainLight.position.set(4, 5, 4);
      this.scene.add(mainLight);

      const rimLight = new THREE.DirectionalLight(0x38bdf8, 0.65);
      rimLight.position.set(-4, 3, -4);
      this.scene.add(rimLight);

      const fillLight = new THREE.DirectionalLight(0xfef08a, 0.35);
      fillLight.position.set(-2, -1, 3);
      this.scene.add(fillLight);

      // Showroom Turntable Pedestal
      const pedGeo = new THREE.CylinderGeometry(2.1, 2.2, 0.14, 36);
      const pedMat = new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.65 });
      const pedestal = new THREE.Mesh(pedGeo, pedMat);
      pedestal.position.y = -0.07;
      this.scene.add(pedestal);

      // Hazard Glowing Turntable Ring
      const ringGeo = new THREE.TorusGeometry(2.05, 0.032, 16, 36);
      const ringMat = new THREE.MeshStandardMaterial({
        color: 0x0284c7,
        roughness: 0.2,
        emissive: 0x0284c7,
        emissiveIntensity: 0.35
      });
      const ring = new THREE.Mesh(ringGeo, ringMat);
      ring.rotation.x = Math.PI / 2;
      ring.position.y = 0;
      this.scene.add(ring);

      // Turntable inner safety cross / grid
      const gridGeo = new THREE.CircleGeometry(1.95, 32);
      const gridMat = new THREE.MeshBasicMaterial({
        color: 0x0f172a,
        wireframe: false
      });
      const gridMesh = new THREE.Mesh(gridGeo, gridMat);
      gridMesh.rotation.x = -Math.PI / 2;
      gridMesh.position.y = 0.002;
      this.scene.add(gridMesh);

      // Car Root Group
      this.carGroup = new THREE.Group();
      this.carGroup.position.y = 0;
      this.carGroup.rotation.y = -0.45; // Hero dynamic angle
      this.scene.add(this.carGroup);

      this.initMaterials();
      this.rebuildCar();

      if (this.options.interactive) {
        this.bindEvents();
      }

      this.animate = this.animate.bind(this);
      this.animate();
    }

    initMaterials() {
      this.materials.paint = new THREE.MeshStandardMaterial({
        color: new THREE.Color(this.options.paintColor),
        roughness: 0.38,
        metalness: 0.35
      });

      this.materials.darkChassis = new THREE.MeshStandardMaterial({
        color: 0x0f172a,
        roughness: 0.7
      });

      this.materials.bumper = new THREE.MeshStandardMaterial({
        color: 0x334155,
        roughness: 0.55,
        metalness: 0.2
      });

      this.materials.windowGlass = new THREE.MeshStandardMaterial({
        color: 0x1e293b,
        roughness: 0.12,
        metalness: 0.85
      });

      this.materials.headlights = new THREE.MeshStandardMaterial({
        color: 0xfef08a,
        emissive: 0xfef08a,
        emissiveIntensity: 0.65,
        roughness: 0.2
      });

      this.materials.taillights = new THREE.MeshStandardMaterial({
        color: 0xdc2626,
        emissive: 0xdc2626,
        emissiveIntensity: 0.65,
        roughness: 0.2
      });

      this.materials.tireRubber = new THREE.MeshStandardMaterial({
        color: 0x18181b,
        roughness: 0.88
      });

      this.materials.rimAlloy = new THREE.MeshStandardMaterial({
        color: 0xe2e8f0,
        roughness: 0.25,
        metalness: 0.75
      });

      this.materials.rimSteel = new THREE.MeshStandardMaterial({
        color: 0x27272a,
        roughness: 0.45,
        metalness: 0.4
      });

      this.materials.hazardOrange = new THREE.MeshStandardMaterial({
        color: 0xf97316,
        roughness: 0.3
      });

      this.materials.beaconBlue = new THREE.MeshStandardMaterial({
        color: 0x38bdf8,
        emissive: 0x0284c7,
        emissiveIntensity: 0.85,
        roughness: 0.15
      });

      this.materials.beaconAmber = new THREE.MeshStandardMaterial({
        color: 0xfbbf24,
        emissive: 0xd97706,
        emissiveIntensity: 0.85,
        roughness: 0.15
      });

      this.materials.chrome = new THREE.MeshStandardMaterial({
        color: 0xffffff,
        roughness: 0.1,
        metalness: 0.95
      });
    }

    rebuildCar() {
      // Clear previous car parts
      if (this.bodyMeshGroup) this.carGroup.remove(this.bodyMeshGroup);
      if (this.wheelsGroup) this.carGroup.remove(this.wheelsGroup);
      if (this.detailGroup) this.carGroup.remove(this.detailGroup);

      this.bodyMeshGroup = new THREE.Group();
      this.wheelsGroup = new THREE.Group();
      this.detailGroup = new THREE.Group();

      this.carGroup.add(this.bodyMeshGroup);
      this.carGroup.add(this.wheelsGroup);
      this.carGroup.add(this.detailGroup);

      const style = this.options.bodyStyle || 'pickup';
      const wheel = this.options.wheelStyle || 'flood';
      const detail = this.options.detail || 'snorkel';

      // Suspension height adjustment based on wheel/flood setting
      let groundClearance = 0.38;
      if (wheel === 'flood') groundClearance = 0.46;
      else if (wheel === 'offroad') groundClearance = 0.42;
      else if (wheel === 'sport') groundClearance = 0.34;

      this.bodyMeshGroup.position.y = groundClearance;
      this.detailGroup.position.y = groundClearance;

      // Build specific body style
      switch (style) {
        case 'suv':
          this.buildSUV();
          break;
        case 'van':
          this.buildVan();
          break;
        case 'compact':
          this.buildCompact();
          break;
        case 'pickup':
        default:
          this.buildPickup();
          break;
      }

      // Build wheels
      this.buildWheels(wheel, groundClearance);

      // Build details / accessories
      this.buildDetails(detail, style);
    }

    buildPickup() {
      const g = this.bodyMeshGroup;

      // 1. Lower Chassis base
      const chassisGeo = new THREE.BoxGeometry(1.24, 0.16, 2.5);
      const chassis = new THREE.Mesh(chassisGeo, this.materials.darkChassis);
      chassis.position.set(0, 0.08, 0);
      g.add(chassis);

      // 2. Hood / Engine Bay
      const hoodGeo = new THREE.BoxGeometry(1.22, 0.38, 0.85);
      const hood = new THREE.Mesh(hoodGeo, this.materials.paint);
      hood.position.set(0, 0.32, 0.78);
      g.add(hood);

      // Front Grille & Bullbar
      const grilleGeo = new THREE.BoxGeometry(1.0, 0.26, 0.08);
      const grille = new THREE.Mesh(grilleGeo, this.materials.bumper);
      grille.position.set(0, 0.3, 1.22);
      g.add(grille);

      // Bullbar tube
      const barGeo = new THREE.BoxGeometry(1.18, 0.12, 0.12);
      const bar = new THREE.Mesh(barGeo, this.materials.bumper);
      bar.position.set(0, 0.22, 1.26);
      g.add(bar);

      // Front bumper
      const fBumpGeo = new THREE.BoxGeometry(1.26, 0.16, 0.14);
      const fBump = new THREE.Mesh(fBumpGeo, this.materials.bumper);
      fBump.position.set(0, 0.12, 1.24);
      g.add(fBump);

      // Rear bumper
      const rBumpGeo = new THREE.BoxGeometry(1.26, 0.16, 0.12);
      const rBump = new THREE.Mesh(rBumpGeo, this.materials.bumper);
      rBump.position.set(0, 0.12, -1.26);
      g.add(rBump);

      // Headlights
      const hlGeo = new THREE.BoxGeometry(0.24, 0.16, 0.04);
      const hlL = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlL.position.set(-0.46, 0.34, 1.22);
      const hlR = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlR.position.set(0.46, 0.34, 1.22);
      g.add(hlL, hlR);

      // 3. Passenger Cabin (Cab)
      const cabBaseGeo = new THREE.BoxGeometry(1.22, 0.38, 0.88);
      const cabBase = new THREE.Mesh(cabBaseGeo, this.materials.paint);
      cabBase.position.set(0, 0.32, -0.06);
      g.add(cabBase);

      const cabTopGeo = new THREE.BoxGeometry(1.14, 0.44, 0.76);
      const cabTop = new THREE.Mesh(cabTopGeo, this.materials.paint);
      cabTop.position.set(0, 0.71, -0.08);
      g.add(cabTop);

      // Windshield (Front Glass)
      const wsGeo = new THREE.BoxGeometry(1.04, 0.34, 0.05);
      const ws = new THREE.Mesh(wsGeo, this.materials.windowGlass);
      ws.rotation.x = 0.26;
      ws.position.set(0, 0.70, 0.32);
      g.add(ws);

      // Rear Cab Glass
      const rGlassGeo = new THREE.BoxGeometry(0.96, 0.32, 0.04);
      const rGlass = new THREE.Mesh(rGlassGeo, this.materials.windowGlass);
      rGlass.position.set(0, 0.71, -0.47);
      g.add(rGlass);

      // Side Windows
      const sideGlassGeo = new THREE.BoxGeometry(0.04, 0.32, 0.62);
      const sgL = new THREE.Mesh(sideGlassGeo, this.materials.windowGlass);
      sgL.position.set(-0.58, 0.71, -0.08);
      const sgR = new THREE.Mesh(sideGlassGeo, this.materials.windowGlass);
      sgR.position.set(0.58, 0.71, -0.08);
      g.add(sgL, sgR);

      // 4. Pickup Cargo Bed
      // Bed floor
      const bedFloorGeo = new THREE.BoxGeometry(1.22, 0.12, 0.82);
      const bedFloor = new THREE.Mesh(bedFloorGeo, this.materials.bumper);
      bedFloor.position.set(0, 0.22, -0.85);
      g.add(bedFloor);

      // Bed side walls
      const bedSideGeo = new THREE.BoxGeometry(0.12, 0.28, 0.82);
      const bedSideL = new THREE.Mesh(bedSideGeo, this.materials.paint);
      bedSideL.position.set(-0.55, 0.36, -0.85);
      const bedSideR = new THREE.Mesh(bedSideGeo, this.materials.paint);
      bedSideR.position.set(0.55, 0.36, -0.85);
      g.add(bedSideL, bedSideR);

      // Bed Tailgate
      const tailGeo = new THREE.BoxGeometry(1.22, 0.28, 0.1);
      const tailgate = new THREE.Mesh(tailGeo, this.materials.paint);
      tailgate.position.set(0, 0.36, -1.22);
      g.add(tailgate);

      // Taillights
      const tlGeo = new THREE.BoxGeometry(0.14, 0.2, 0.04);
      const tlL = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlL.position.set(-0.52, 0.36, -1.27);
      const tlR = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlR.position.set(0.52, 0.36, -1.27);
      g.add(tlL, tlR);

      // Stylized Rollbar behind cab
      const rbTopGeo = new THREE.BoxGeometry(1.12, 0.08, 0.08);
      const rbTop = new THREE.Mesh(rbTopGeo, this.materials.bumper);
      rbTop.position.set(0, 0.94, -0.52);
      const rbLegGeo = new THREE.BoxGeometry(0.08, 0.48, 0.08);
      const rbLegL = new THREE.Mesh(rbLegGeo, this.materials.bumper);
      rbLegL.position.set(-0.52, 0.68, -0.52);
      const rbLegR = new THREE.Mesh(rbLegGeo, this.materials.bumper);
      rbLegR.position.set(0.52, 0.68, -0.52);
      g.add(rbTop, rbLegL, rbLegR);
    }

    buildSUV() {
      const g = this.bodyMeshGroup;

      // 1. Lower Chassis
      const chassisGeo = new THREE.BoxGeometry(1.28, 0.16, 2.55);
      const chassis = new THREE.Mesh(chassisGeo, this.materials.darkChassis);
      chassis.position.set(0, 0.08, 0);
      g.add(chassis);

      // 2. Front Hood
      const hoodGeo = new THREE.BoxGeometry(1.26, 0.42, 0.85);
      const hood = new THREE.Mesh(hoodGeo, this.materials.paint);
      hood.position.set(0, 0.34, 0.82);
      g.add(hood);

      // Grille & Bumper
      const grilleGeo = new THREE.BoxGeometry(1.04, 0.28, 0.08);
      const grille = new THREE.Mesh(grilleGeo, this.materials.bumper);
      grille.position.set(0, 0.32, 1.26);
      g.add(grille);

      const fBumpGeo = new THREE.BoxGeometry(1.3, 0.18, 0.14);
      const fBump = new THREE.Mesh(fBumpGeo, this.materials.bumper);
      fBump.position.set(0, 0.14, 1.28);
      g.add(fBump);

      const rBumpGeo = new THREE.BoxGeometry(1.3, 0.18, 0.14);
      const rBump = new THREE.Mesh(rBumpGeo, this.materials.bumper);
      rBump.position.set(0, 0.14, -1.28);
      g.add(rBump);

      // Headlights
      const hlGeo = new THREE.BoxGeometry(0.24, 0.18, 0.04);
      const hlL = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlL.position.set(-0.48, 0.36, 1.26);
      const hlR = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlR.position.set(0.48, 0.36, 1.26);
      g.add(hlL, hlR);

      // 3. Enclosed Cabin Body (Full Length)
      const cabBaseGeo = new THREE.BoxGeometry(1.26, 0.42, 1.6);
      const cabBase = new THREE.Mesh(cabBaseGeo, this.materials.paint);
      cabBase.position.set(0, 0.34, -0.38);
      g.add(cabBase);

      const cabTopGeo = new THREE.BoxGeometry(1.18, 0.52, 1.55);
      const cabTop = new THREE.Mesh(cabTopGeo, this.materials.paint);
      cabTop.position.set(0, 0.77, -0.36);
      g.add(cabTop);

      // Windshield
      const wsGeo = new THREE.BoxGeometry(1.08, 0.4, 0.05);
      const ws = new THREE.Mesh(wsGeo, this.materials.windowGlass);
      ws.rotation.x = 0.28;
      ws.position.set(0, 0.76, 0.44);
      g.add(ws);

      // Rear Window
      const rwGeo = new THREE.BoxGeometry(1.04, 0.36, 0.04);
      const rw = new THREE.Mesh(rwGeo, this.materials.windowGlass);
      rw.position.set(0, 0.76, -1.15);
      g.add(rw);

      // Side Windows (Left & Right)
      const sideGlassGeo = new THREE.BoxGeometry(0.04, 0.36, 1.4);
      const sgL = new THREE.Mesh(sideGlassGeo, this.materials.windowGlass);
      sgL.position.set(-0.6, 0.77, -0.36);
      const sgR = new THREE.Mesh(sideGlassGeo, this.materials.windowGlass);
      sgR.position.set(0.6, 0.77, -0.36);
      g.add(sgL, sgR);

      // Taillights
      const tlGeo = new THREE.BoxGeometry(0.12, 0.3, 0.04);
      const tlL = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlL.position.set(-0.52, 0.48, -1.2);
      const tlR = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlR.position.set(0.52, 0.48, -1.2);
      g.add(tlL, tlR);

      // Roof Rails
      const railGeo = new THREE.BoxGeometry(0.06, 0.06, 1.35);
      const rlL = new THREE.Mesh(railGeo, this.materials.bumper);
      rlL.position.set(-0.52, 1.05, -0.36);
      const rlR = new THREE.Mesh(railGeo, this.materials.bumper);
      rlR.position.set(0.52, 1.05, -0.36);
      g.add(rlL, rlR);

      // Mounted Rear Spare Tire
      const spareGeo = new THREE.CylinderGeometry(0.28, 0.28, 0.16, 24);
      const spare = new THREE.Mesh(spareGeo, this.materials.tireRubber);
      spare.rotation.x = Math.PI / 2;
      spare.position.set(0, 0.52, -1.28);
      const rimGeo = new THREE.CylinderGeometry(0.16, 0.16, 0.17, 16);
      const rim = new THREE.Mesh(rimGeo, this.materials.rimAlloy);
      rim.rotation.x = Math.PI / 2;
      rim.position.set(0, 0.52, -1.28);
      g.add(spare, rim);
    }

    buildVan() {
      const g = this.bodyMeshGroup;

      // 1. Lower Chassis
      const chassisGeo = new THREE.BoxGeometry(1.3, 0.16, 2.7);
      const chassis = new THREE.Mesh(chassisGeo, this.materials.darkChassis);
      chassis.position.set(0, 0.08, 0);
      g.add(chassis);

      // 2. High Van Main Body
      const vanBodyGeo = new THREE.BoxGeometry(1.28, 0.95, 2.65);
      const vanBody = new THREE.Mesh(vanBodyGeo, this.materials.paint);
      vanBody.position.set(0, 0.62, 0);
      g.add(vanBody);

      // Sloped front nose
      const noseGeo = new THREE.BoxGeometry(1.26, 0.38, 0.26);
      const nose = new THREE.Mesh(noseGeo, this.materials.paint);
      nose.position.set(0, 0.35, 1.34);
      g.add(nose);

      // Front Bumper
      const fBumpGeo = new THREE.BoxGeometry(1.32, 0.18, 0.12);
      const fBump = new THREE.Mesh(fBumpGeo, this.materials.bumper);
      fBump.position.set(0, 0.14, 1.44);
      g.add(fBump);

      const rBumpGeo = new THREE.BoxGeometry(1.32, 0.18, 0.12);
      const rBump = new THREE.Mesh(rBumpGeo, this.materials.bumper);
      rBump.position.set(0, 0.14, -1.34);
      g.add(rBump);

      // Headlights & Grille
      const hlGeo = new THREE.BoxGeometry(0.24, 0.18, 0.04);
      const hlL = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlL.position.set(-0.48, 0.36, 1.45);
      const hlR = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlR.position.set(0.48, 0.36, 1.45);
      g.add(hlL, hlR);

      // Windshield
      const wsGeo = new THREE.BoxGeometry(1.12, 0.44, 0.04);
      const ws = new THREE.Mesh(wsGeo, this.materials.windowGlass);
      ws.rotation.x = 0.22;
      ws.position.set(0, 0.78, 1.28);
      g.add(ws);

      // Passenger Side Windows (Split panels)
      const winGeo = new THREE.BoxGeometry(0.04, 0.34, 0.65);
      const winL1 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winL1.position.set(-0.65, 0.78, 0.65);
      const winL2 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winL2.position.set(-0.65, 0.78, -0.2);
      const winL3 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winL3.position.set(-0.65, 0.78, -0.85);

      const winR1 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winR1.position.set(0.65, 0.78, 0.65);
      const winR2 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winR2.position.set(0.65, 0.78, -0.2);
      const winR3 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winR3.position.set(0.65, 0.78, -0.85);
      g.add(winL1, winL2, winL3, winR1, winR2, winR3);

      // Rear Twin Door Windows
      const rwGeo = new THREE.BoxGeometry(0.45, 0.34, 0.04);
      const rwL = new THREE.Mesh(rwGeo, this.materials.windowGlass);
      rwL.position.set(-0.28, 0.78, -1.33);
      const rwR = new THREE.Mesh(rwGeo, this.materials.windowGlass);
      rwR.position.set(0.28, 0.78, -1.33);
      g.add(rwL, rwR);

      // Vertical Taillights
      const tlGeo = new THREE.BoxGeometry(0.1, 0.38, 0.04);
      const tlL = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlL.position.set(-0.55, 0.52, -1.33);
      const tlR = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlR.position.set(0.55, 0.52, -1.33);
      g.add(tlL, tlR);
    }

    buildCompact() {
      const g = this.bodyMeshGroup;

      // 1. Lower Chassis
      const chassisGeo = new THREE.BoxGeometry(1.2, 0.14, 2.25);
      const chassis = new THREE.Mesh(chassisGeo, this.materials.darkChassis);
      chassis.position.set(0, 0.07, 0);
      g.add(chassis);

      // 2. Aerodynamic Hood
      const hoodGeo = new THREE.BoxGeometry(1.18, 0.32, 0.75);
      const hood = new THREE.Mesh(hoodGeo, this.materials.paint);
      hood.position.set(0, 0.28, 0.72);
      g.add(hood);

      // Front Bumpers
      const fBumpGeo = new THREE.BoxGeometry(1.22, 0.16, 0.12);
      const fBump = new THREE.Mesh(fBumpGeo, this.materials.bumper);
      fBump.position.set(0, 0.12, 1.12);
      g.add(fBump);

      const rBumpGeo = new THREE.BoxGeometry(1.22, 0.16, 0.12);
      const rBump = new THREE.Mesh(rBumpGeo, this.materials.bumper);
      rBump.position.set(0, 0.12, -1.12);
      g.add(rBump);

      // Sleek Headlights
      const hlGeo = new THREE.BoxGeometry(0.22, 0.14, 0.04);
      const hlL = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlL.position.set(-0.44, 0.30, 1.10);
      const hlR = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlR.position.set(0.44, 0.30, 1.10);
      g.add(hlL, hlR);

      // 3. Cabin Base & Roof Greenhouse
      const cabBaseGeo = new THREE.BoxGeometry(1.18, 0.32, 1.45);
      const cabBase = new THREE.Mesh(cabBaseGeo, this.materials.paint);
      cabBase.position.set(0, 0.28, -0.34);
      g.add(cabBase);

      const cabTopGeo = new THREE.BoxGeometry(1.08, 0.42, 1.25);
      const cabTop = new THREE.Mesh(cabTopGeo, this.materials.paint);
      cabTop.position.set(0, 0.62, -0.32);
      g.add(cabTop);

      // Windshield
      const wsGeo = new THREE.BoxGeometry(0.98, 0.38, 0.04);
      const ws = new THREE.Mesh(wsGeo, this.materials.windowGlass);
      ws.rotation.x = 0.38;
      ws.position.set(0, 0.58, 0.34);
      g.add(ws);

      // Sloped Hatchback Rear Glass
      const rwGeo = new THREE.BoxGeometry(0.96, 0.34, 0.04);
      const rw = new THREE.Mesh(rwGeo, this.materials.windowGlass);
      rw.rotation.x = -0.38;
      rw.position.set(0, 0.58, -0.96);
      g.add(rw);

      // Side Windows
      const sideGlassGeo = new THREE.BoxGeometry(0.04, 0.3, 1.1);
      const sgL = new THREE.Mesh(sideGlassGeo, this.materials.windowGlass);
      sgL.position.set(-0.55, 0.62, -0.32);
      const sgR = new THREE.Mesh(sideGlassGeo, this.materials.windowGlass);
      sgR.position.set(0.55, 0.62, -0.32);
      g.add(sgL, sgR);

      // Taillights
      const tlGeo = new THREE.BoxGeometry(0.2, 0.14, 0.04);
      const tlL = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlL.position.set(-0.45, 0.36, -1.08);
      const tlR = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlR.position.set(0.45, 0.36, -1.08);
      g.add(tlL, tlR);
    }

    buildWheels(wheelStyle, groundClearance) {
      const g = this.wheelsGroup;

      let radius = 0.32;
      let width = 0.2;
      let rimRadius = 0.18;
      let rimMat = this.materials.rimAlloy;

      if (wheelStyle === 'flood') {
        radius = 0.38;
        width = 0.24;
        rimRadius = 0.19;
        rimMat = this.materials.rimSteel;
      } else if (wheelStyle === 'offroad') {
        radius = 0.34;
        width = 0.22;
        rimRadius = 0.18;
        rimMat = this.materials.rimAlloy;
      } else if (wheelStyle === 'steelie') {
        radius = 0.31;
        width = 0.18;
        rimRadius = 0.16;
        rimMat = this.materials.rimSteel;
      } else if (wheelStyle === 'sport') {
        radius = 0.30;
        width = 0.18;
        rimRadius = 0.20;
        rimMat = this.materials.chrome;
      }

      // Wheel positions (Front-Left, Front-Right, Rear-Left, Rear-Right)
      const wheelPos = [
        { x: -0.64, y: radius, z: 0.78 },
        { x: 0.64, y: radius, z: 0.78 },
        { x: -0.64, y: radius, z: -0.78 },
        { x: 0.64, y: radius, z: -0.78 }
      ];

      wheelPos.forEach((p, idx) => {
        const wheelObj = new THREE.Group();
        wheelObj.position.set(p.x, p.y, p.z);

        // Rubber tire
        const tireGeo = new THREE.CylinderGeometry(radius, radius, width, 24);
        const tire = new THREE.Mesh(tireGeo, this.materials.tireRubber);
        tire.rotation.z = Math.PI / 2;
        wheelObj.add(tire);

        // Tread lugs for flood and offroad wheels
        if (wheelStyle === 'flood' || wheelStyle === 'offroad') {
          const lugCount = 8;
          for (let i = 0; i < lugCount; i++) {
            const angle = (i / lugCount) * Math.PI * 2;
            const lugGeo = new THREE.BoxGeometry(width + 0.02, 0.04, 0.05);
            const lug = new THREE.Mesh(lugGeo, this.materials.tireRubber);
            lug.position.set(0, Math.sin(angle) * (radius - 0.01), Math.cos(angle) * (radius - 0.01));
            wheelObj.add(lug);
          }
        }

        // Rim
        const rimGeo = new THREE.CylinderGeometry(rimRadius, rimRadius, width + 0.01, 16);
        const rim = new THREE.Mesh(rimGeo, rimMat);
        rim.rotation.z = Math.PI / 2;
        wheelObj.add(rim);

        // Center hub cap
        const hubGeo = new THREE.CylinderGeometry(rimRadius * 0.45, rimRadius * 0.45, width + 0.03, 12);
        const hub = new THREE.Mesh(hubGeo, this.materials.darkChassis);
        hub.rotation.z = Math.PI / 2;
        wheelObj.add(hub);

        // Heavy-duty mudflaps behind rear wheels
        if (idx >= 2 && (wheelStyle === 'flood' || wheelStyle === 'offroad')) {
          const flapGeo = new THREE.BoxGeometry(0.04, 0.22, 0.16);
          const flap = new THREE.Mesh(flapGeo, this.materials.tireRubber);
          flap.position.set(p.x > 0 ? 0.02 : -0.02, 0.06, -0.24);
          wheelObj.add(flap);
        }

        g.add(wheelObj);
      });
    }

    buildDetails(detail, style) {
      const g = this.detailGroup;

      if (detail === 'snorkel') {
        // High-water intake snorkel mounted on right A-pillar
        const pipeGeo = new THREE.CylinderGeometry(0.035, 0.035, 0.58, 12);
        const pipe = new THREE.Mesh(pipeGeo, this.materials.bumper);
        pipe.position.set(0.64, 0.62, 0.42);

        // Snorkel top ram airhead
        const headGeo = new THREE.BoxGeometry(0.12, 0.1, 0.14);
        const head = new THREE.Mesh(headGeo, this.materials.darkChassis);
        head.position.set(0.64, 0.94, 0.44);

        g.add(pipe, head);
      } else if (detail === 'roofrack') {
        // Roof luggage/gear rack with 4 flood spotlights
        const isVan = style === 'van';
        const isCompact = style === 'compact';
        const rackY = isVan ? 1.15 : (isCompact ? 0.88 : 1.05);
        const rackZ = isVan ? 0.0 : (isCompact ? -0.28 : -0.25);
        const rackLen = isVan ? 1.4 : 0.9;

        const rackGeo = new THREE.BoxGeometry(1.08, 0.1, rackLen);
        const rack = new THREE.Mesh(rackGeo, this.materials.bumper);
        rack.position.set(0, rackY, rackZ);
        g.add(rack);

        // 4 Floodlights on the rack front bar
        for (let i = 0; i < 4; i++) {
          const x = -0.38 + i * 0.25;
          const lightGeo = new THREE.BoxGeometry(0.14, 0.12, 0.08);
          const light = new THREE.Mesh(lightGeo, this.materials.headlights);
          light.position.set(x, rackY + 0.12, rackZ + rackLen / 2 + 0.04);
          g.add(light);
        }
      } else if (detail === 'beacon') {
        // Dual emergency flood warning strobe lightbar
        const isVan = style === 'van';
        const isCompact = style === 'compact';
        const beaconY = isVan ? 1.14 : (isCompact ? 0.88 : 1.02);
        const beaconZ = isVan ? 0.5 : (isCompact ? -0.1 : 0.0);

        const baseGeo = new THREE.BoxGeometry(0.85, 0.06, 0.14);
        const base = new THREE.Mesh(baseGeo, this.materials.darkChassis);
        base.position.set(0, beaconY, beaconZ);

        const amberGeo = new THREE.BoxGeometry(0.36, 0.08, 0.12);
        const amberLight = new THREE.Mesh(amberGeo, this.materials.beaconAmber);
        amberLight.position.set(-0.2, beaconY + 0.06, beaconZ);

        const blueGeo = new THREE.BoxGeometry(0.36, 0.08, 0.12);
        const blueLight = new THREE.Mesh(blueGeo, this.materials.beaconBlue);
        blueLight.position.set(0.2, beaconY + 0.06, beaconZ);

        g.add(base, amberLight, blueLight);
      }
    }

    updateConfig(newOptions = {}) {
      let needsRebuild = false;

      if (newOptions.paintColor && newOptions.paintColor !== this.options.paintColor) {
        this.options.paintColor = newOptions.paintColor;
        this.materials.paint.color.set(this.options.paintColor);
      }

      if (newOptions.bodyStyle && newOptions.bodyStyle !== this.options.bodyStyle) {
        this.options.bodyStyle = newOptions.bodyStyle;
        needsRebuild = true;
      }

      if (newOptions.wheelStyle && newOptions.wheelStyle !== this.options.wheelStyle) {
        this.options.wheelStyle = newOptions.wheelStyle;
        needsRebuild = true;
      }

      if (newOptions.detail && newOptions.detail !== this.options.detail) {
        this.options.detail = newOptions.detail;
        needsRebuild = true;
      }

      if (needsRebuild) {
        this.rebuildCar();
      }

      this.render();
    }

    resetToDefaults() {
      this.updateConfig({
        bodyStyle: 'pickup',
        paintColor: '#ea580c',
        wheelStyle: 'flood',
        detail: 'snorkel'
      });
    }

    resize() {
      if (!this.canvas || !this.renderer || !this.camera) return;
      const rect = this.canvas.getBoundingClientRect();
      const width = rect.width || this.canvas.width || 340;
      const height = rect.height || this.canvas.height || 300;

      this.camera.aspect = width / height;
      this.camera.updateProjectionMatrix();
      this.renderer.setSize(width, height, false);
      this.render();
    }

    bindEvents() {
      const getX = (e) => (e.touches && e.touches[0] ? e.touches[0].clientX : e.clientX);

      const onStart = (e) => {
        this.isDragging = true;
        this.prevMouseX = getX(e);
        this.rotationVelocity = 0;
      };

      const onMove = (e) => {
        if (!this.isDragging) return;
        const currentX = getX(e);
        const delta = currentX - this.prevMouseX;
        this.prevMouseX = currentX;
        this.carGroup.rotation.y += delta * 0.015;
        this.rotationVelocity = delta * 0.015;
      };

      const onEnd = () => {
        this.isDragging = false;
      };

      this.canvas.addEventListener('mousedown', onStart);
      window.addEventListener('mousemove', onMove);
      window.addEventListener('mouseup', onEnd);

      this.canvas.addEventListener('touchstart', onStart, { passive: true });
      window.addEventListener('touchmove', onMove, { passive: true });
      window.addEventListener('touchend', onEnd);

      // Responsive resize handling
      this._onResize = () => this.resize();
      window.addEventListener('resize', this._onResize);
      if (typeof ResizeObserver !== 'undefined' && this.canvas.parentElement) {
        this._resizeObserver = new ResizeObserver(() => this.resize());
        this._resizeObserver.observe(this.canvas.parentElement);
      }
    }

    animate() {
      this.animationFrameId = requestAnimationFrame(this.animate);

      const time = this.clock.getElapsedTime();

      // Subtle suspension idling vibration
      if (this.carGroup) {
        this.carGroup.position.y = Math.sin(time * 3.5) * 0.006;

        if (this.isDragging) {
          // Handled in mousemove
        } else if (Math.abs(this.rotationVelocity) > 0.0005) {
          this.carGroup.rotation.y += this.rotationVelocity;
          this.rotationVelocity *= 0.92;
        } else if (this.options.autoRotate) {
          this.carGroup.rotation.y += 0.005;
        }
      }

      this.render();
    }

    render() {
      if (this.renderer && this.scene && this.camera) {
        this.renderer.render(this.scene, this.camera);
      }
    }

    getSnapshot(quality = 0.82) {
      if (!this.renderer) return '';
      const currentRot = this.carGroup.rotation.y;
      this.carGroup.rotation.y = -0.52; // Crisp 3/4 hero presentation angle
      this.render();
      const dataUrl = this.canvas.toDataURL('image/jpeg', quality);
      this.carGroup.rotation.y = currentRot;
      return dataUrl;
    }

    getConfig() {
      return {
        bodyStyle: this.options.bodyStyle,
        paintColor: this.options.paintColor,
        wheelStyle: this.options.wheelStyle,
        detail: this.options.detail
      };
    }

    destroy() {
      if (this.animationFrameId) {
        cancelAnimationFrame(this.animationFrameId);
      }
      if (this._onResize) {
        window.removeEventListener('resize', this._onResize);
      }
      if (this._resizeObserver) {
        this._resizeObserver.disconnect();
      }
      if (this.renderer) {
        this.renderer.dispose();
      }
    }
  }

  window.Car3D = Car3D;
})();
