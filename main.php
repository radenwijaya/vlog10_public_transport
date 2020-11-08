<?php
	class main
	{
		var $sim_result;
		function __construct($simulation_count)
		{
			for ($i=0; $i<$simulation_count; $i++)
			{
				printf("%s \n\n", "begin_simulation");
				
				$sim=new simulation(40, 8, 60);
				
				$sim->run();
				$this->sim_result[]=$sim->conclude();
				
				printf("%s %d \n", "total_passenger", $sim->total_passenger);
				printf("%s \n\n", "end_simulation");
			}
		}
		
		function conclude()
		{
			$result=[];
			$ongkos_angkot=2500;
			$bensin=600;
			
			foreach ($this->sim_result as $sim_result)
			{
				foreach ($sim_result as $id => ['speed' => $speed, 'passenger' => $passenger, 'distance' => $distance])
				{
					if (!isset($result[$id]))
					{
						$result[$id]['penumpang']=$passenger;
						$result[$id]['jarak']=$distance/1000;
					}
					else
					{
						$result[$id]['penumpang']+=$passenger;
						$result[$id]['jarak']+=$distance/1000; /* km */
					}
					
					$result[$id]['kecepatan']=$speed*3.6; /* km per jam */									
				}
			}
			
			foreach ($result as $id => ['penumpang' => $passenger, 'jarak' => $distance])
			{
				$result[$id]['pendapatan']=$passenger*$ongkos_angkot;
				$result[$id]['pengeluaran']=$distance*$bensin;
				$result[$id]['keuntungan']=$result[$id]['pendapatan']-$result[$id]['pengeluaran'];
			}
				
			var_dump($result);
		}
	}
	
	class simulation
	{
		var $lap_length, $shift_length, $pass_spawn;
		
		var $track, $vehicles; //variable to hold the location of passenger
		
		var $total_passenger;
		
		function __construct($lap_length, $shift_length, $pass_spawn)
		// shift_length input is in hour, but processed in seconds
		// lap distance is in km, but processed in metre
		// pass_spawn is every how many seconds a passenger is spawned randomly
		{
			$this->lap_length=$lap_length*1000;
			$this->shift_length=$shift_length*3600; //convert to second
			$this->pass_spawn=$pass_spawn;
			
			$this->track=[];
			for ($i=0; $i<=$this->lap_length; $i++)
				$this->track[$i]=[];
			
			$this->vehicles=[];
						
			$this->spawn_vehicles();						
		}
		
		function spawn_vehicles($id=0)
		{
			$this->vehicles[]=new angkot($id, 0, 5, $this->lap_length, 12);
			$this->vehicles[]=new angkot($id+1, 0, 7, $this->lap_length, 12);
			$this->vehicles[]=new angkot($id+2, 0, 9, $this->lap_length, 12);
			$this->vehicles[]=new angkot($id+3, 0, 11, $this->lap_length, 12);
			$this->vehicles[]=new angkot($id+4, 0, 13, $this->lap_length, 12);
			$this->vehicles[]=new angkot($id+5, 0, 15, $this->lap_length, 12);
			
			return $id+6;
		}
		
		function spawn_student($school, $count=20)
		{
			for ($i=0; $i<$count; $i++)
			{
				$penumpang=new student($this->lap_length, 'berangkat', $school);
				$this->track[$penumpang->location][]=$penumpang;
				printf("%s %d \n", "Murid Dijalan ", $penumpang->location);
			}
		}
		
		function spawn_school($school, $count=20)
		{
			for ($i=0; $i<$count; $i++)
			{
				$penumpang=new student($this->lap_length, 'pulang', $school);
				$this->track[$penumpang->location][]=$penumpang;
				printf("%s %d \n", "Budal Sekolah ", $penumpang->location);
			}			
		}
		
		function run()
		{						
			$total_passenger=0;
			
			$student_disembark=3600;
			$school_end=21600;
			$school_location=10000;
			
//			$vc=6;

			for ($i=1; $i<=$this->shift_length; $i++)
			{
				if ($i % 3600 == 0)
					printf("%s \n\n", "Pergantian Jam");
				
//				if ($i==14400)
//					$vc=$this->spawn_vehicles($vc);
				
				if ($i==$student_disembark)				
				{
					$this->spawn_student($school_location);
				}
				if ($i==$school_end)
				{
					$this->spawn_school($school_location);
				}				
				
				if ($i % $this->pass_spawn == 0)
				{
					$penumpang=new penumpang($this->lap_length);
					$this->track[$penumpang->location][]=$penumpang;
					printf("%s %d \n\n", "Penumpang Baru ", $penumpang->location);
					
					$total_passenger++;
				}
								
				foreach ($this->vehicles as $vehicle)
				{
					$vehicle->drive($this->track);
				}
			}
			
			$this->total_passenger=$total_passenger;
		}
		
		function conclude()
		{
			$result=[];
			
			foreach ($this->vehicles as $vehicle)
			{
				$result[$vehicle->id]['passenger']=$vehicle->carried;
				$result[$vehicle->id]['speed']=$vehicle->speed;
				$result[$vehicle->id]['distance']=$vehicle->distance;
				$result[$vehicle->id]['lap']=$vehicle->lap;
			}						
			
			return $result;
		}
	}
	
	class angkot
	{
		var $position;
		var $lap_length;
		var $speed, $capacity;
		var $passengers;
		var $id;
		
		var $carried;
		var $lap;
		var $distance;
		
		function __construct($id, $start_position, $speed, $lap_length, $capacity)
		{
			$this->position=$start_position;
			$this->lap_length=$lap_length;
			$this->speed=$speed;
			$this->capacity=$capacity;
			$this->id=$id;
			
			$this->lap=0;
			$this->distance=0;
			
			$this->passengers=[];
			$this->carried=0;
		}
				
		function drive(&$track)
		/*
		 * Drive is moving the vehicle per second, metre by metre
		 */
		{
			$from=$this->position;
			$to=$from+$this->speed;
			
			$this->distance+=$this->speed;;
			
			for ($l=$from; $l<=$to; $l++)
			{
				/* turun dulu seperti semua angkot */
				$exiting=[];
				foreach ($this->passengers as $key => $passenger)
				{
					if ($passenger->is_exiting($l))
					{
						$exiting[$key]=$l;						
					}
				}
				
				foreach ($exiting as $key => $ln)
				{
					unset($this->passengers[$key]);
					printf("%d %s %d %s %d \n", $this->id, 'Turun m', $ln, 'Isi', count($this->passengers));
				}
					
				foreach ($track[$l % $this->lap_length] as $key => $passenger)
				{
					if (count($this->passengers)<$this->capacity)
					{
						$this->carried++;
						$this->passengers[]=$passenger;					
						unset($track[$l][$key]);
						
						printf("%d %s %d %s %d \n", $this->id, 'Naik m', $l, 'Isi', count($this->passengers));						
					}
					else 
						break;					
				}				
			}
			
			if ($this->position>$this->lap_length)
				$this->lap++;
			
			$this->position=$to % $this->lap_length;			
		}
	}
	
	class penumpang
	{
		var $distance;
		
		function __construct($lap_length)
		{
			$this->distance=random_int(500, 5000);
			$this->location=random_int(0, $lap_length);
			
			$this->exit=($this->distance+$this->location) % $lap_length;
		}
		
		function is_exiting($location)
		{
			return ($location==$this->exit);
		}
	}
	
	class student extends penumpang
	{
		var $distance;
		
		function __construct($lap_length, $mode, $school)
		{			
			if ($mode=='berangkat')
			{
				$this->distance=random_int(500, 5000);
				$this->location=random_int(0, $lap_length);
				
				$this->exit=$school;
			}
			else 
			{
				$this->distance=random_int(500, 5000);
				$this->location=$school;
				
				$this->exit=($this->distance+$this->location) % $lap_length;				
			}
		}
		
		function is_exiting($location)
		{
			return ($location==$this->exit);
		}
	}
	
	$main=new main(30);
	$main->conclude();
?>